<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\BeverageOptions;
use App\Support\ComboOptions;
use App\Support\JuiceOptions;
use App\Support\TableSessionLock;
use App\Support\TakeawayPackaging;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View { $orders=Order::query()->with(['tableSession.restaurantTable','orderItems.product','handledBy'])->when($request->status,fn($q,$s)=>$q->whereIn('status',OrderStatus::tryFrom($s)?->storedValues() ?? [$s]),fn($q)=>$q->where('status','!=',OrderStatus::COMPLETED->value))->oldest()->get(); return view('admin.orders.index',['orders'=>$orders,'statuses'=>OrderStatus::operationalCases(),'selectedStatus'=>$request->status]); }
    public function pending(): JsonResponse { $orders=Order::query()->where('status',OrderStatus::PENDING->value)->with(['tableSession.restaurantTable','handledBy'])->oldest()->get(); return response()->json(['count'=>$orders->count(),'ids'=>$orders->pluck('id')->values(),'orders'=>$orders->map(fn(Order $o)=>['id'=>$o->id,'location'=>$o->type?->value==='PARA_LLEVAR'?'PARA LLEVAR':($o->type?->value==='DOMICILIO'?'DOMICILIO':'MESA '.($o->tableSession?->restaurantTable?->number??'—')),'time'=>$o->created_at?->format('H:i'),'responsible'=>$o->handledBy?->name??'Pedido QR'])->values()]); }
    public function create(): View { return view('admin.orders.create-v2',['products'=>Product::query()->with(['category','beverageOptions'])->where('is_available',true)->whereHas('category',fn($q)=>$q->where('is_active',true))->orderBy('name')->get(),'tables'=>RestaurantTable::query()->where('status','!=',TableStatus::CLEANING->value)->orderBy('number')->get(),'categories'=>Category::query()->where('is_active',true)->orderBy('name')->get(),'orderTypes'=>OrderType::cases(),'comboBeverages'=>collect(ComboOptions::types())->mapWithKeys(fn($label,$type)=>[$type=>['label'=>$label,'flavors'=>ComboOptions::availableFlavors($type)]])->all(),'comboPrice'=>ComboOptions::PRICE,'juiceFruits'=>$this->selectableJuiceFruits()]); }
    public function store(Request $request): RedirectResponse {
        $v=$request->validate([
            'type'=>['required',Rule::enum(OrderType::class)],
            'table_id'=>['nullable','integer','exists:restaurant_tables,id'],
            'customer_name'=>['nullable','string','max:255'],
            'customer_phone'=>['nullable','string','max:30'],
            'delivery_address'=>['nullable','string','max:255'],
            'delivery_reference'=>['nullable','string','max:255'],
            'delivery_fee'=>['nullable','regex:/^\\d+$/','max:9999999999'],
            'items'=>['required','array'],
            'items.*'=>['nullable','integer','min:0','max:99'],
            'portion_pairing'=>['nullable','array'],
            'portion_pairing.*'=>['nullable','integer','exists:products,id'],
            'item_notes'=>['nullable','array'],
            'item_notes.*'=>['nullable','string','max:500'],
            'juice_preparation'=>['nullable','array'],
            'juice_preparation.*'=>['nullable',Rule::in([JuiceOptions::WATER,JuiceOptions::MILK])],
            'juice_fruit'=>['nullable','array'],
            'juice_fruit.*'=>['nullable',Rule::in(array_keys(JuiceOptions::FRUITS))],
            'juice_other_fruit'=>['nullable','array'],
            'juice_other_fruit.*'=>['nullable','string','max:100'],
            'beverage_option'=>['nullable','array'],
            'beverage_option.*'=>['nullable','string','max:100'],
            'combo'=>['nullable','array'],
            'combo.*'=>['nullable',Rule::in([ComboOptions::NO,ComboOptions::YES])],
            'combo_beverage_type'=>['nullable','array'],
            'combo_beverage_type.*'=>['nullable',Rule::in(array_keys(ComboOptions::types()))],
            'combo_beverage_flavor'=>['nullable','array'],
            'combo_beverage_flavor.*'=>['nullable','string','max:100'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $v['items']=array_filter($v['items'],static fn($q)=>(int)$q!==0);
        $v['items']=validator(['items'=>$v['items']],['items'=>['required','array','min:1'],'items.*'=>['required','integer','min:1','max:99']])->validate()['items'];
        $type=OrderType::from($v['type']);
        if($type===OrderType::TABLE&&empty($v['table_id']))throw ValidationException::withMessages(['table_id'=>['Selecciona una mesa para un pedido en mesa.']]);
        if($type!==OrderType::TABLE&&!empty($v['table_id']))throw ValidationException::withMessages(['table_id'=>['Los pedidos que no son en mesa no pueden tener una mesa asociada.']]);
        if($type===OrderType::DELIVERY){
            validator($v,[
                'customer_name'=>['required','string','max:255'],
                'customer_phone'=>['required','string','max:30'],
                'delivery_address'=>['required','string','max:255'],
                'delivery_fee'=>['required','regex:/^\\d+$/','max:9999999999'],
            ])->validate();
        }

        $order=DB::transaction(function()use($v,$type){
            $session=null;
            if($type===OrderType::TABLE){
                $table=RestaurantTable::query()->lockForUpdate()->findOrFail($v['table_id']);
                if($table->status===TableStatus::CLEANING)throw ValidationException::withMessages(['table_id'=>['La mesa seleccionada no está disponible para recibir pedidos.']]);
                // Orden de bloqueo mesa → sesión (ver TableSessionLock): si la cuenta se
                // está cobrando, se espera a que termine y, si quedó cerrada, se abre otra.
                $session=TableSessionLock::lockActiveSessionOf($table);
                if(!$session)$session=TableSession::create(['restaurant_table_id'=>$table->id,'status'=>TableSessionStatus::Active,'started_at'=>now()]);
                $table->update(['status'=>TableStatus::OCCUPIED]);
            }

            $order=Order::create(['table_session_id'=>$session?->id,'type'=>$type,'status'=>OrderStatus::PENDING,'subtotal'=>0,'packaging_fee'=>0,'delivery_fee'=>(int)($v['delivery_fee']??0),'tax'=>0,'total'=>0,'customer_name'=>$v['customer_name']??null,'customer_phone'=>$v['customer_phone']??null,'delivery_address'=>$v['delivery_address']??null,'delivery_reference'=>$v['delivery_reference']??null,'notes'=>$v['notes']??null,'handled_by_user_id'=>Auth::id()]);
                        $round=$order->rounds()->create(['number'=>1,'created_by_user_id'=>Auth::id()]);
            $subtotal=0;
            $packagingFee=0;
            $createdItems=[];

            foreach($v['items'] as $productId=>$quantity){
                $p=Product::query()->with(['category','beverageOptions'])->findOrFail($productId);
                if(!$p->is_available)throw ValidationException::withMessages(['items'=>["El producto '{$p->name}' no está disponible."]]);
                if(!$p->category?->is_active)throw ValidationException::withMessages(['items'=>["El producto '{$p->name}' pertenece a una categoría deshabilitada."]]);
                $quantity=(int)$quantity;
                $price=(int)$p->price;
                $notes=$v['item_notes'][$productId]??null;

                if(JuiceOptions::isJuice($p)){
                    $prep=$v['juice_preparation'][$productId]??null;
                    $fruit=$v['juice_fruit'][$productId]??null;
                    $other=$v['juice_other_fruit'][$productId]??null;
                    $details=$notes;
                    if($fruit===JuiceOptions::OTHER&&trim((string)$other)===''){$other=$details;$details=null;}
                    $price=JuiceOptions::price($prep);
                    $notes=JuiceOptions::buildNote($prep,$fruit,$other,$details);
                }elseif(BeverageOptions::hasOptions($p)){
                    $notes=BeverageOptions::buildNote($p,$v['beverage_option'][$productId]??null,$notes);
                }elseif(!empty($v['beverage_option'][$productId])){
                    throw ValidationException::withMessages(['items'=>["El producto '{$p->name}' no admite una opción de bebida."]]);
                }

                $combo=ComboOptions::validate($p,$v['combo'][$productId]??ComboOptions::NO,$v['combo_beverage_type'][$productId]??null,$v['combo_beverage_flavor'][$productId]??null);
                if($combo['combo']===ComboOptions::YES){
                    $price+=ComboOptions::PRICE;
                    $comboNote=ComboOptions::buildNote($combo);
                    $notes=trim(implode(' · ',array_filter([$comboNote,$notes])));
                }

                $line=$price*$quantity;
                $packagingFee+=TakeawayPackaging::fee($p,$quantity,$type->value);
                $createdItems[$p->id]=OrderItem::create(['order_id'=>$order->id,'order_round_id'=>$round->id,'product_id'=>$p->id,'quantity'=>$quantity,'unit_price'=>$price,'total'=>$line,'notes'=>$notes,'sent_at'=>null]);
                $subtotal+=$line;
            }

            $this->applyPortionPairings($order, $v['portion_pairing'] ?? [], $createdItems);
            $order->update(['subtotal'=>$subtotal,'packaging_fee'=>$packagingFee,'tax'=>0,'total'=>$subtotal+$packagingFee+(int)$order->delivery_fee+(int)$order->tax]);
            $order->statusHistories()->create(['previous_status'=>null,'new_status'=>OrderStatus::PENDING->value,'changed_by_user_id'=>Auth::id(),'changed_at'=>now()]);
            return $order;
        });

        $route=Auth::user()?->role?->value==='MESERO'?'waiter.orders':'admin.orders.show';
        return redirect()->route($route,$route==='admin.orders.show'?$order:[])->with('success',"Pedido #{$order->id} creado y enviado a caja.");
    }
    public function add(Order $order): View
    {
        $this->ensureAdditionAllowed($order);

        return view('admin.orders.add-v2', [
            'order' => $order->load(['tableSession.restaurantTable', 'orderItems.product.category']),
            'products' => Product::query()
                ->with(['category', 'beverageOptions'])
                ->where('is_available', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'comboBeverages' => collect(ComboOptions::types())
                ->mapWithKeys(fn ($label, $type) => [
                    $type => [
                        'label' => $label,
                        'flavors' => ComboOptions::availableFlavors($type),
                    ],
                ])
                ->all(),
            'juiceFruits' => $this->selectableJuiceFruits(),
        ]);
    }

    /**
     * Frutas que se pueden elegir para el jugo natural: solo las disponibles
     * y que el backend reconoce (JuiceOptions::buildNote las valida igual).
     */
    private function selectableJuiceFruits(): array
    {
        return array_intersect_key(JuiceOptions::availableFruits(), JuiceOptions::FRUITS);
    }

    public function storeAddition(Request $request, Order $order): RedirectResponse
    {
        $this->ensureAdditionAllowed($order);

        $v = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'portion_pairing' => ['nullable', 'array'],
            'portion_pairing.*' => ['nullable', 'integer', 'exists:products,id'],
            'item_notes' => ['nullable', 'array'],
            'item_notes.*' => ['nullable', 'string', 'max:500'],
            'juice_preparation' => ['nullable', 'array'],
            'juice_preparation.*' => ['nullable', Rule::in([JuiceOptions::WATER, JuiceOptions::MILK])],
            'juice_fruit' => ['nullable', 'array'],
            'juice_fruit.*' => ['nullable', Rule::in(array_keys(JuiceOptions::FRUITS))],
            'juice_other_fruit' => ['nullable', 'array'],
            'juice_other_fruit.*' => ['nullable', 'string', 'max:100'],
            'beverage_option' => ['nullable', 'array'],
            'beverage_option.*' => ['nullable', 'string', 'max:100'],
            'combo' => ['nullable', 'array'],
            'combo.*' => ['nullable', Rule::in([ComboOptions::NO, ComboOptions::YES])],
            'combo_beverage_type' => ['nullable', 'array'],
            'combo_beverage_type.*' => ['nullable', Rule::in(array_keys(ComboOptions::types()))],
            'combo_beverage_flavor' => ['nullable', 'array'],
            'combo_beverage_flavor.*' => ['nullable', 'string', 'max:100'],
        ]);

        $v['items'] = array_filter($v['items'], static fn ($q) => (int) $q !== 0);
        $v['items'] = validator(
            ['items' => $v['items']],
            ['items' => ['required', 'array', 'min:1'], 'items.*' => ['required', 'integer', 'min:1', 'max:99']]
        )->validate()['items'];

        DB::transaction(function () use ($v, $order): void {
            // Mismo orden de bloqueo que el cobro de la mesa (mesa → sesión → pedido).
            // Las condiciones se vuelven a comprobar con las filas bloqueadas: la cuenta
            // pudo cobrarse entre que se abrió el formulario y se envió la adición.
            $lockedSession = $order->type === OrderType::TABLE && $order->table_session_id
                ? TableSessionLock::lockSession($order->table_session_id)
                : null;

            $order = Order::query()->lockForUpdate()->with('orderItems')->findOrFail($order->id);

            if ($order->type === OrderType::TABLE) {
                if (! TableSessionLock::isActive($lockedSession)) {
                    throw ValidationException::withMessages(['order' => [TableSessionLock::SESSION_CLOSED]]);
                }

                $order->setRelation('tableSession', $lockedSession);
            }

            $this->ensureAdditionAllowed($order);

            $nextRound = ((int) $order->rounds()->max('number')) + 1;
            $round = $order->rounds()->create([
                'number' => $nextRound,
                'created_by_user_id' => Auth::id(),
            ]);

            $createdItems = [];
            $additionPackagingFee = 0;

            foreach ($v['items'] as $productId => $quantity) {
                $p = Product::query()->with(['category', 'beverageOptions'])->findOrFail($productId);

                if (! $p->is_available) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$p->name}' no está disponible."],
                    ]);
                }

                if (! $p->category?->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$p->name}' pertenece a una categoría deshabilitada."],
                    ]);
                }

                $quantity = (int) $quantity;
                $price = (int) $p->price;
                $notes = $v['item_notes'][$productId] ?? null;

                if (JuiceOptions::isJuice($p)) {
                    $prep = $v['juice_preparation'][$productId] ?? null;
                    $fruit = $v['juice_fruit'][$productId] ?? null;
                    $other = $v['juice_other_fruit'][$productId] ?? null;
                    $details = $notes;

                    if ($fruit === JuiceOptions::OTHER && trim((string) $other) === '') {
                        $other = $details;
                        $details = null;
                    }

                    $price = JuiceOptions::price($prep);
                    $notes = JuiceOptions::buildNote($prep, $fruit, $other, $details);
                } elseif (BeverageOptions::hasOptions($p)) {
                    $notes = BeverageOptions::buildNote($p, $v['beverage_option'][$productId] ?? null, $notes);
                } elseif (! empty($v['beverage_option'][$productId])) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$p->name}' no admite una opción de bebida."],
                    ]);
                }

                $combo = ComboOptions::validate(
                    $p,
                    $v['combo'][$productId] ?? ComboOptions::NO,
                    $v['combo_beverage_type'][$productId] ?? null,
                    $v['combo_beverage_flavor'][$productId] ?? null
                );

                if ($combo['combo'] === ComboOptions::YES) {
                    $price += ComboOptions::PRICE;
                    $comboNote = ComboOptions::buildNote($combo);
                    $notes = trim(implode(' · ', array_filter([$comboNote, $notes])));
                }

                $createdItems[$p->id] = OrderItem::create([
                    'order_id' => $order->id,
                    'order_round_id' => $round->id,
                    'product_id' => $p->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total' => $price * $quantity,
                    'notes' => $notes,
                    'sent_at' => null,
                ]);
                $additionPackagingFee += TakeawayPackaging::fee($p, $quantity, $order->type->value);
            }

            $this->applyPortionPairings($order, $v['portion_pairing'] ?? [], $createdItems);

            $order->load('orderItems.product');
            $subtotal = $order->orderItems->sum('total');
            // Solo se suma el icopor de los productos de esta adición: el que ya tenía el
            // pedido se respeta tal cual (no se recalcula retroactivamente con reglas nuevas).
            $packagingFee = (int) $order->packaging_fee + $additionPackagingFee;

            $previousStatus = $order->status;
            $order->update([
                'subtotal' => $subtotal,
                'packaging_fee' => $packagingFee,
                'total' => $subtotal + $packagingFee + (int) $order->delivery_fee + (int) $order->tax,
                'status' => OrderStatus::PENDING,
            ]);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus?->value,
                'new_status' => OrderStatus::PENDING->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => "Adición #{$nextRound}",
            ]);
        });

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()
            ->route($route, $route === 'admin.orders.show' ? $order : [])
            ->with('success', "Adición agregada al pedido #{$order->id}. Quedó pendiente de impresión.");
    }

    private function applyPortionPairings(Order $order, array $pairings, array $createdItems): void
    {
        foreach ($pairings as $portionProductId => $targetProductId) {
            $portionProductId = (int) $portionProductId;
            $targetProductId = (int) $targetProductId;

            if ($portionProductId === 0 || $targetProductId === 0 || $portionProductId === $targetProductId) {
                continue;
            }

            $portionItem = $createdItems[$portionProductId] ?? null;

            if (! $portionItem) {
                throw ValidationException::withMessages([
                    'portion_pairing' => ['La porción seleccionada no pertenece a los productos de este pedido.'],
                ]);
            }

            $portionItem->loadMissing('product');

            if (! $portionItem->product?->is_portion) {
                throw ValidationException::withMessages([
                    'portion_pairing' => ['Solo los productos marcados como porción pueden tener un acompañamiento.'],
                ]);
            }

            $targetItem = OrderItem::query()
                ->where('order_id', $order->id)
                ->where('product_id', $targetProductId)
                ->latest('id')
                ->first();

            if (! $targetItem) {
                throw ValidationException::withMessages([
                    'portion_pairing' => ['El producto seleccionado para acompañar no pertenece al pedido.'],
                ]);
            }

            $targetItem->loadMissing('product');

            if ($targetItem->product?->is_portion) {
                throw ValidationException::withMessages([
                    'portion_pairing' => ['Una porción no puede acompañar a otra porción.'],
                ]);
            }

            $portionItem->update(['paired_order_item_id' => $targetItem->id]);
        }
    }

    private function ensureAdditionAllowed(Order $order): void
    {
        $order->loadMissing(['tableSession']);

        if ($order->status === OrderStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido ya está cerrado y no admite nuevas adiciones.'],
            ]);
        }

        if ($order->type === OrderType::TABLE) {
            if (! $order->tableSession || $order->tableSession->status !== TableSessionStatus::Active) {
                throw ValidationException::withMessages([
                    'order' => ['La sesión de esta mesa ya está cerrada.'],
                ]);
            }

            // La mesa sigue abierta hasta que se cobra la cuenta (también POR COBRAR).
            if (! in_array($order->status->operational(), [
                OrderStatus::PENDING,
                OrderStatus::DELIVERED,
                OrderStatus::TO_COLLECT,
            ], true)) {
                throw ValidationException::withMessages([
                    'order' => ['La mesa no está disponible para recibir una nueva adición.'],
                ]);
            }

            return;
        }

        // PARA_LLEVAR: también POR COBRAR (pasa a POR COBRAR al imprimirse, igual que antes
        // admitía adiciones ya impreso). DOMICILIO: solo hasta que "🛵 Salió" (POR COBRAR).
        $allowed = $order->type === OrderType::TAKEAWAY
            ? [OrderStatus::PENDING, OrderStatus::DELIVERED, OrderStatus::TO_COLLECT]
            : [OrderStatus::PENDING, OrderStatus::DELIVERED];

        if (! in_array($order->status->operational(), $allowed, true)) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido ya salió o está por cobrar y no admite nuevas adiciones.'],
            ]);
        }
    }

    public function show(Order $order): View {$order->load(['tableSession.restaurantTable','orderItems.product','statusHistories.changedBy','handledBy','deliveredBy','paidBy']);return view('admin.orders.show',['order'=>$order,'statuses'=>OrderStatus::operationalCases()]);}
    public function updateStatus(Request $request,Order $order): RedirectResponse {$request->validate(['status'=>['required',Rule::enum(OrderStatus::class)]]);
        // Ya no hay cambios manuales de estado: PENDIENTE → ENTREGADO ocurre al imprimir las
        // comandas, y ENTREGADO → POR COBRAR al imprimir la cuenta (mesa) o con "🛵 Salió" (domicilio).
        throw ValidationException::withMessages(['status'=>['El estado del pedido no se cambia manualmente: pasa a ENTREGADO al imprimir las comandas.']]);}
    /**
     * "🛵 Salió": el domicilio ya entregado (impreso) sale con el repartidor y queda
     * POR COBRAR. Quien marca la salida queda como responsable de la entrega.
     */
    public function dispatch(Order $order): RedirectResponse
    {
        if ($order->type !== OrderType::DELIVERY) {
            throw ValidationException::withMessages([
                'status' => ['Solo los domicilios pueden marcarse como "Salió".'],
            ]);
        }

        if ($order->status->operational() !== OrderStatus::DELIVERED) {
            throw ValidationException::withMessages([
                'status' => ['El domicilio debe estar ENTREGADO (comandas impresas) antes de marcar que salió.'],
            ]);
        }

        $prev = $order->status;
        DB::transaction(function () use ($order, $prev): void {
            $order->update([
                'status' => OrderStatus::TO_COLLECT,
                'delivered_by_user_id' => Auth::id(),
                'delivered_at' => now(),
            ]);
            $order->statusHistories()->create([
                'previous_status' => $prev->value,
                'new_status' => OrderStatus::TO_COLLECT->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => 'Domicilio salió.',
            ]);
        });

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()
            ->route($route, $route === 'admin.orders.show' ? $order : [])
            ->with('success', 'Domicilio marcado como "Salió". Queda POR COBRAR.');
    }
}
