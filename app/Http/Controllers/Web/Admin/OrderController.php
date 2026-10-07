<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRound;
use App\Models\OrderSauce;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sauce;
use App\Models\TableSession;
use App\Support\BeverageOptions;
use App\Support\ComboOptions;
use App\Support\JuiceOptions;
use App\Support\OrderSauces;
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
    public function index(Request $request): View { $orders=Order::query()->with(['tableSession.restaurantTable','orderItems.product','handledBy'])->when($request->status,fn($q,$s)=>$q->whereIn('status',OrderStatus::tryFrom($s)?->storedValues() ?? [$s]),fn($q)=>$q->whereIn('status',OrderStatus::activeValues()))->oldest()->get(); return view('admin.orders.index',['orders'=>$orders,'statuses'=>OrderStatus::operationalCases(),'selectedStatus'=>$request->status]); }
    public function pending(): JsonResponse { $orders=Order::query()->where('status',OrderStatus::PENDING->value)->with(['tableSession.restaurantTable','handledBy'])->oldest()->get(); return response()->json(['count'=>$orders->count(),'ids'=>$orders->pluck('id')->values(),'orders'=>$orders->map(fn(Order $o)=>['id'=>$o->id,'location'=>$o->type?->value==='PARA_LLEVAR'?'PARA LLEVAR':($o->type?->value==='DOMICILIO'?'DOMICILIO':'MESA '.($o->tableSession?->restaurantTable?->number??'—')),'time'=>$o->created_at?->format('H:i'),'responsible'=>$o->handledBy?->name??'Pedido QR'])->values()]); }
    public function create(): View { return view('admin.orders.create-v2',['products'=>Product::query()->with(['category','beverageOptions'])->where('is_available',true)->whereHas('category',fn($q)=>$q->where('is_active',true))->orderBy('name')->get(),'tables'=>RestaurantTable::query()->where('status','!=',TableStatus::CLEANING->value)->orderBy('number')->get(),'categories'=>Category::query()->where('is_active',true)->orderBy('name')->get(),'orderTypes'=>OrderType::cases(),'comboBeverages'=>collect(ComboOptions::types())->mapWithKeys(fn($label,$type)=>[$type=>['label'=>$label,'flavors'=>ComboOptions::availableFlavors($type)]])->all(),'comboPrice'=>ComboOptions::PRICE,'juiceFruits'=>$this->selectableJuiceFruits(),'sauces'=>Sauce::query()->selectable()->get()]); }
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
        ]+OrderSauces::rules());
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
            $lineSauces=[];
            OrderSauces::assertOnlyOrderedProducts($v['items'],$v);

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
                // Una línea por configuración de salsas: con "Todos iguales" (o sin salsas) queda
                // una sola línea ×N, igual que antes; las unidades personalizadas distintas se separan.
                foreach(OrderSauces::unitGroups($quantity,$v,(int)$productId) as $group){
                    $item=OrderItem::create(['order_id'=>$order->id,'order_round_id'=>$round->id,'product_id'=>$p->id,'quantity'=>$group['quantity'],'unit_price'=>$price,'total'=>$price*$group['quantity'],'notes'=>$notes,'sent_at'=>null]);
                    $createdItems[$p->id][]=$item;
                    $lineSauces[]=[$item,$group['sauces']];
                }
                $subtotal+=$line;
            }

            $this->applyPortionPairings($order, $v['portion_pairing'] ?? [], $createdItems);
            // Salsas (gratuitas, solo PARA_LLEVAR/DOMICILIO): no cambian subtotal, icopor ni total.
            OrderSauces::attach($order, $round, $lineSauces, $v['general_sauces'] ?? []);
            $order->update(['subtotal'=>$subtotal,'packaging_fee'=>$packagingFee,'tax'=>0,'total'=>$subtotal+$packagingFee+(int)$order->delivery_fee+(int)$order->tax]);
            $order->statusHistories()->create(['previous_status'=>null,'new_status'=>OrderStatus::PENDING->value,'changed_by_user_id'=>Auth::id(),'changed_at'=>now()]);
            return $order;
        });

        $route=Auth::user()?->role?->value==='MESERO'?'waiter.orders':'admin.orders.show';
        return redirect()->route($route,$route==='admin.orders.show'?$order:[])->with('success',"Pedido #{$order->id} creado y enviado a caja.");
    }
    /** El antiguo "＋ Agregar" quedó dentro de "✏️ Editar pedido". */
    public function add(Order $order): RedirectResponse
    {
        return redirect()->route('admin.orders.edit', $order);
    }

    /**
     * "✏️ Editar pedido": productos actuales (quitar, sustituir, cambiar cantidad o salsas)
     * y catálogo para agregar productos. Solo PENDIENTE o POR COBRAR (domicilio: antes de Salió).
     */
    public function edit(Order $order): View
    {
        $this->ensureAdditionAllowed($order);

        return view('admin.orders.add-v2', [
            'order' => $order->load([
                'tableSession.restaurantTable',
                'orderItems.product.category',
                'orderItems.sauces.sauce',
                'orderItems.pairedOrderItem.product',
                'generalSauces.sauce',
            ]),
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
            'sauces' => Sauce::query()->selectable()->get(),
            'saucesEnabled' => OrderSauces::allowedFor($order->type),
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

    /**
     * Guarda una edición del pedido como una ronda nueva:
     * - void[order_item_id] = unidades que se quitan de esa línea (se anulan, nunca se borran;
     *   si se quitan solo algunas, la línea se divide en activa + anulada);
     * - items[...] y demás campos = productos que se agregan (mismo flujo que las adiciones,
     *   con salsas, combos, jugos, opciones de bebida y porciones);
     * - portion_target[order_item_id] = a qué producto pasa a acompañar una porción cuyo
     *   producto se quita por completo;
     * - reason = motivo, obligatorio si se quita algo que ya se envió a cocina.
     * Sustituir = quitar + agregar en la misma edición; el pedido conserva su número.
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->ensureAdditionAllowed($order);

        $v = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'void' => ['nullable', 'array'],
            'void.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'portion_target' => ['nullable', 'array'],
            'portion_target.*' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
            'remove_general_sauces' => ['nullable', 'array'],
            'remove_general_sauces.*' => ['integer', 'distinct'],
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
        ] + OrderSauces::rules());

        $v['items'] = array_filter($v['items'] ?? [], static fn ($q) => (int) $q !== 0);
        $v['items'] = validator(
            ['items' => $v['items']],
            ['items' => ['array'], 'items.*' => ['required', 'integer', 'min:1', 'max:99']]
        )->validate()['items'];
        $voids = array_filter(array_map('intval', $v['void'] ?? []), static fn (int $q): bool => $q > 0);
        $reason = trim((string) ($v['reason'] ?? ''));
        $removeGeneral = array_values(array_unique(array_map('intval', $v['remove_general_sauces'] ?? [])));

        if ($v['items'] === [] && $voids === [] && $removeGeneral === [] && empty($v['general_sauces'])) {
            throw ValidationException::withMessages([
                'items' => ['Agrega, quita o sustituye al menos un producto.'],
            ]);
        }

        $pendingPrint = DB::transaction(function () use ($v, $voids, $removeGeneral, $reason, $order): bool {
            // Mismo orden de bloqueo que el cobro de la mesa (mesa → sesión → pedido).
            // Las condiciones se vuelven a comprobar con las filas bloqueadas: la cuenta
            // pudo cobrarse o el pedido cancelarse mientras se editaba.
            $lockedSession = $order->type === OrderType::TABLE && $order->table_session_id
                ? TableSessionLock::lockSession($order->table_session_id)
                : null;

            $order = Order::query()->lockForUpdate()->with(['orderItems.product', 'orderItems.sauces'])->findOrFail($order->id);

            if ($order->type === OrderType::TABLE) {
                if (! TableSessionLock::isActive($lockedSession)) {
                    throw ValidationException::withMessages(['order' => [TableSessionLock::SESSION_CLOSED]]);
                }

                $order->setRelation('tableSession', $lockedSession);
            }

            $this->ensureAdditionAllowed($order);

            $previousTotal = (int) $order->total;
            $previousStatus = $order->status;
            $lines = $order->orderItems->keyBy('id');

            // --- Validar lo que se quita -------------------------------------------------
            foreach ($voids as $lineId => $units) {
                $line = $lines->get((int) $lineId);
                if (! $line) {
                    throw ValidationException::withMessages(['void' => ['Uno de los productos que quieres quitar ya no está en el pedido. Actualiza la pantalla.']]);
                }
                if ($units > (int) $line->quantity) {
                    throw ValidationException::withMessages(['void' => ["No puedes quitar más de {$line->quantity} × {$line->product?->name}."]]);
                }
                if ($line->sent_at !== null && $reason === '') {
                    throw ValidationException::withMessages(['reason' => ["Escribe el motivo del cambio: {$line->product?->name} ya se envió a cocina."]]);
                }
            }

            // Salsas generales que se quitan: la no enviada se borra; la enviada se anula.
            $generals = $order->generalSauces()->with('sauce')->get()->keyBy('id');
            foreach ($removeGeneral as $sauceRowId) {
                $general = $generals->get($sauceRowId);
                if (! $general) {
                    throw ValidationException::withMessages(['remove_general_sauces' => ['Una de las salsas generales que quieres quitar ya no está en el pedido. Actualiza la pantalla.']]);
                }
                if ($general->sent_at !== null && $reason === '') {
                    throw ValidationException::withMessages(['reason' => ["Escribe el motivo del cambio: la salsa general {$general->sauce?->name} ya se envió a cocina."]]);
                }
            }

            // Porciones cuyo producto se quita por completo: se quitan también o se reasignan.
            $fullyVoided = collect($voids)->filter(fn (int $units, $lineId): bool => $units === (int) $lines->get((int) $lineId)->quantity)->keys()->map(fn ($id): int => (int) $id);
            $reassign = [];
            foreach ($lines as $portion) {
                if (! $portion->paired_order_item_id || ! $fullyVoided->contains($portion->paired_order_item_id) || $fullyVoided->contains($portion->id)) {
                    continue;
                }
                $targetId = (int) ($v['portion_target'][$portion->id] ?? 0);
                $target = $lines->get($targetId);
                if (! $target || $fullyVoided->contains($targetId) || $target->id === $portion->id || $target->product?->is_portion) {
                    throw ValidationException::withMessages([
                        'portion_target' => ["La porción {$portion->product?->name} acompaña a {$lines->get($portion->paired_order_item_id)?->product?->name}, que se va a quitar: quita también la porción o elige a qué producto acompañará."],
                    ]);
                }
                $reassign[$portion->id] = $targetId;
            }

            $nextRound = ((int) $order->rounds()->max('number')) + 1;
            $round = $order->rounds()->create([
                'number' => $nextRound,
                'created_by_user_id' => Auth::id(),
            ]);

            // --- Quitar (anular) -----------------------------------------------------------
            $removed = [];
            $packagingDelta = 0;
            $kitchenAffected = false;
            foreach ($voids as $lineId => $units) {
                $line = $lines->get((int) $lineId);
                $removed[] = $this->voidLine($line, $units, $round);
                $packagingDelta -= TakeawayPackaging::fee($line->product, $units, $order->type->value);
                $kitchenAffected = $kitchenAffected || $line->sent_at !== null;
            }
            foreach ($reassign as $portionId => $targetId) {
                $lines->get($portionId)->update(['paired_order_item_id' => $targetId]);
            }

            $removedGeneral = [];
            foreach ($removeGeneral as $sauceRowId) {
                $general = $generals->get($sauceRowId);
                if ($general->sent_at === null) {
                    // Cocina nunca la recibió: se quita directamente (queda en la nota de la edición).
                    $general->delete();
                    $removedGeneral[] = $general->sauce?->name;
                } else {
                    // Ya enviada: no se borra; sale como "❌ NO PREPARAR" en la comanda de cambio.
                    $general->update(['voided_at' => now()]);
                    $removedGeneral[] = $general->sauce?->name.' (ya enviada)';
                    $kitchenAffected = true;
                }
            }

            // --- Agregar (mismo flujo que las adiciones) -----------------------------------
            $added = [];
            $addedGeneral = Sauce::query()->whereIn('id', $v['general_sauces'] ?? [])->orderBy('sort_order')->pluck('name')->all();
            if ($v['items'] !== [] || ! empty($v['general_sauces'])) {
                [$added, $additionPackagingFee] = $this->addRoundItems($order, $round, $v);
                $packagingDelta += $additionPackagingFee;
                $kitchenAffected = $kitchenAffected || $added !== [];
            }

            if (! $order->orderItems()->exists()) {
                throw ValidationException::withMessages([
                    'void' => ['El pedido no puede quedar vacío. Para retirarlo por completo usa ❌ Cancelar pedido.'],
                ]);
            }

            $subtotal = (int) $order->orderItems()->sum('total');
            // Icopor incremental: se suma el de lo agregado y se resta el de lo quitado,
            // sin recalcular el resto del pedido y sin bajar nunca de 0.
            $packagingFee = max(0, (int) $order->packaging_fee + $packagingDelta);
            $total = $subtotal + $packagingFee + (int) $order->delivery_fee + (int) $order->tax;

            // Queda PENDIENTE mientras haya algo que imprimir para cocina (productos nuevos o
            // un "❌ NO PREPARAR"); si ya no queda nada por imprimir, sigue POR COBRAR.
            $order->refresh();
            $newStatus = $order->hasPendingKitchenChanges() ? OrderStatus::PENDING : OrderStatus::TO_COLLECT;

            $order->update([
                'subtotal' => $subtotal,
                'packaging_fee' => $packagingFee,
                'total' => $total,
                'status' => $newStatus,
            ]);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus?->value,
                'new_status' => $newStatus->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => $this->editSummary($nextRound - 1, $removed, $added, $previousTotal, $total, $reason, $removedGeneral, $addedGeneral),
            ]);

            return $newStatus === OrderStatus::PENDING && ($kitchenAffected || $addedGeneral !== []);
        });

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()
            ->route($route, $route === 'admin.orders.show' ? $order : [])
            ->with('success', $pendingPrint
                ? "Pedido #{$order->id} actualizado. Quedó pendiente de impresión."
                : "Pedido #{$order->id} actualizado.");
    }

    /**
     * Anula $units unidades de una línea activa. Si son todas, la línea se marca anulada; si
     * son algunas, la línea se divide: conserva las unidades que quedan y se crea una línea
     * anulada con las retiradas (mismo producto, precio, notas, salsas, ronda y envío).
     */
    private function voidLine(OrderItem $line, int $units, OrderRound $round): OrderItem
    {
        $voidData = [
            'voided_at' => now(),
            'voided_by_user_id' => Auth::id(),
            'voided_round_id' => $round->id,
        ];

        if ($units === (int) $line->quantity) {
            $line->update($voidData);

            return $line;
        }

        $remaining = (int) $line->quantity - $units;
        $line->update(['quantity' => $remaining, 'total' => (int) $line->unit_price * $remaining]);

        $voided = $line->replicate(['voided_at', 'voided_by_user_id', 'voided_round_id', 'void_sent_at']);
        $voided->fill($voidData + ['quantity' => $units, 'total' => (int) $line->unit_price * $units]);
        $voided->save();

        foreach ($line->sauces as $sauce) {
            OrderSauce::create([
                'order_id' => $sauce->order_id,
                'order_item_id' => $voided->id,
                'order_round_id' => $sauce->order_round_id,
                'sauce_id' => $sauce->sauce_id,
                'placement' => $sauce->placement,
            ]);
        }

        return $voided->setRelation('product', $line->product);
    }

    /**
     * Crea las líneas que se agregan en una ronda (adición o edición): disponibilidad,
     * jugos, opciones de bebida, combos, salsas por unidad, porciones e icopor.
     *
     * @return array{0: array<int, OrderItem>, 1: int} [líneas creadas, icopor de lo agregado]
     */
    private function addRoundItems(Order $order, OrderRound $round, array $v): array
    {
        $createdItems = [];
        $added = [];
        $lineSauces = [];
        $additionPackagingFee = 0;
        OrderSauces::assertOnlyOrderedProducts($v['items'], $v);

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

            // Una línea por configuración de salsas (ver OrderSauces::unitGroups).
            foreach (OrderSauces::unitGroups($quantity, $v, (int) $productId) as $group) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'order_round_id' => $round->id,
                    'product_id' => $p->id,
                    'quantity' => $group['quantity'],
                    'unit_price' => $price,
                    'total' => $price * $group['quantity'],
                    'notes' => $notes,
                    'sent_at' => null,
                ]);
                $createdItems[$p->id][] = $item;
                $added[] = $item->setRelation('product', $p);
                $lineSauces[] = [$item, $group['sauces']];
            }
            $additionPackagingFee += TakeawayPackaging::fee($p, $quantity, $order->type->value);
        }

        $this->applyPortionPairings($order, $v['portion_pairing'] ?? [], $createdItems);
        // Salsas de esta ronda (gratuitas, solo PARA_LLEVAR/DOMICILIO).
        OrderSauces::attach($order, $round, $lineSauces, $v['general_sauces'] ?? []);

        return [$added, $additionPackagingFee];
    }

    /**
     * Nota del historial de una edición: qué se quitó (precio histórico), qué se agregó
     * (precio actual), diferencia, total anterior → nuevo y motivo.
     *
     * @param  array<int, OrderItem>  $removed
     * @param  array<int, OrderItem>  $added
     * @param  array<int, string>  $removedGeneral
     * @param  array<int, string>  $addedGeneral
     */
    private function editSummary(int $editNumber, array $removed, array $added, int $previousTotal, int $total, string $reason, array $removedGeneral = [], array $addedGeneral = []): string
    {
        $money = static fn (int $value): string => ($value < 0 ? '−' : '').'$'.number_format(abs($value), 0, ',', '.');
        $describe = static fn (OrderItem $item): string => "{$item->quantity} × {$item->product?->name} ({$money((int) $item->total)})";

        $parts = ["✏️ Edición #{$editNumber}"];
        if ($removed !== []) {
            $parts[] = '❌ Quitado: '.implode(', ', array_map($describe, $removed));
        }
        if ($added !== []) {
            $parts[] = '✅ Agregado: '.implode(', ', array_map($describe, $added));
        }
        if ($removedGeneral !== []) {
            $parts[] = '❌ Salsas generales quitadas: '.implode(', ', $removedGeneral);
        }
        if ($addedGeneral !== []) {
            $parts[] = '✅ Salsas generales agregadas: '.implode(', ', $addedGeneral);
        }
        $difference = $total - $previousTotal;
        $parts[] = 'Diferencia: '.($difference > 0 ? '+' : '').$money($difference);
        $parts[] = "Total: {$money($previousTotal)} → {$money($total)}";
        if ($reason !== '') {
            $parts[] = "Motivo: {$reason}";
        }

        return implode(' · ', $parts);
    }

    private function applyPortionPairings(Order $order, array $pairings, array $createdItems): void
    {
        foreach ($pairings as $portionProductId => $targetProductId) {
            $portionProductId = (int) $portionProductId;
            $targetProductId = (int) $targetProductId;

            if ($portionProductId === 0 || $targetProductId === 0 || $portionProductId === $targetProductId) {
                continue;
            }

            // Un producto puede haber quedado en varias líneas (salsas personalizadas por unidad):
            // todas las líneas de la porción acompañan al producto elegido.
            $portionItems = $createdItems[$portionProductId] ?? [];
            $portionItem = $portionItems[0] ?? null;

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
                ->whereNull('voided_at')
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

            foreach ($portionItems as $line) {
                $line->update(['paired_order_item_id' => $targetItem->id]);
            }
        }
    }

    private function ensureAdditionAllowed(Order $order): void
    {
        $order->loadMissing(['tableSession']);

        if ($order->status === OrderStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido ya está cerrado (TERMINADO) y no se puede editar.'],
            ]);
        }

        if ($order->status === OrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido está cancelado y no se puede editar.'],
            ]);
        }

        if ($order->type === OrderType::TABLE) {
            if (! $order->tableSession || $order->tableSession->status !== TableSessionStatus::Active) {
                throw ValidationException::withMessages([
                    'order' => ['La sesión de esta mesa ya está cerrada.'],
                ]);
            }

            // La mesa sigue abierta hasta que se cobra la cuenta (también POR COBRAR).
            if (! $order->status->isActive()) {
                throw ValidationException::withMessages([
                    'order' => ['La mesa no está disponible para editar este pedido.'],
                ]);
            }

            return;
        }

        // PARA_LLEVAR: PENDIENTE o POR COBRAR. DOMICILIO: igual, pero solo hasta que se
        // marca "🛵 Salió".
        if (! $order->status->isActive()) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido no se puede editar.'],
            ]);
        }

        if ($order->type === OrderType::DELIVERY && $order->dispatched_at !== null) {
            throw ValidationException::withMessages([
                'order' => ['Este domicilio ya salió y no se puede editar.'],
            ]);
        }
    }

    public function show(Order $order): View {$order->load(['tableSession.restaurantTable','orderItems.product','orderItems.sauces.sauce','generalSauces.sauce','statusHistories.changedBy','handledBy','deliveredBy','dispatchedBy','paidBy']);return view('admin.orders.show',['order'=>$order,'statuses'=>OrderStatus::operationalCases()]);}
    public function updateStatus(Request $request,Order $order): RedirectResponse {$request->validate(['status'=>['required',Rule::enum(OrderStatus::class)]]);
        // Ya no hay cambios manuales de estado: PENDIENTE → POR COBRAR ocurre al imprimir las
        // comandas, POR COBRAR → TERMINADO al registrar el pago y CANCELADO con "Cancelar pedido".
        throw ValidationException::withMessages(['status'=>['El estado del pedido no se cambia manualmente: pasa a POR COBRAR al imprimir las comandas.']]);}
    /**
     * "🛵 Salió": marca (no estado) de que el domicilio salió con el repartidor. El pedido
     * sigue POR COBRAR. Quien marca la salida queda como responsable de la entrega
     * (reporte "Entregas por usuario"), como antes.
     */
    public function dispatch(Order $order): RedirectResponse
    {
        if ($order->type !== OrderType::DELIVERY) {
            throw ValidationException::withMessages([
                'status' => ['Solo los domicilios pueden marcarse como "Salió".'],
            ]);
        }

        DB::transaction(function () use ($order): void {
            // Estado y marca se comprueban sobre la fila bloqueada: un doble clic o dos
            // usuarios a la vez no registran dos salidas, y un pedido cancelado no sale.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status->operational() !== OrderStatus::TO_COLLECT) {
                throw ValidationException::withMessages([
                    'status' => ['El domicilio debe estar POR COBRAR (comandas impresas) antes de marcar que salió.'],
                ]);
            }

            if ($order->dispatched_at !== null) {
                throw ValidationException::withMessages([
                    'status' => ['Este domicilio ya fue marcado como "Salió".'],
                ]);
            }

            $order->update([
                'dispatched_at' => now(),
                'dispatched_by_user_id' => Auth::id(),
                'delivered_by_user_id' => Auth::id(),
                'delivered_at' => now(),
            ]);
            // Queda en el historial como evento, sin cambiar el estado.
            $order->statusHistories()->create([
                'previous_status' => $order->status->value,
                'new_status' => $order->status->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => '🛵 Domicilio salió.',
            ]);
        });

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()
            ->route($route, $route === 'admin.orders.show' ? $order : [])
            ->with('success', 'Domicilio marcado como "🛵 Salió". Sigue POR COBRAR.');
    }

    /**
     * Cancela un pedido PENDIENTE o POR COBRAR (ADMIN y MESERO) con un motivo obligatorio.
     * El pedido no se elimina: conserva productos e historial, pero queda fuera de ventas,
     * cobros, pendientes y del cierre. Si la mesa queda sin pedidos activos, se libera igual
     * que al cobrar la cuenta.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $reason = trim((string) $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Escribe el motivo de la cancelación.',
        ])['reason']);

        DB::transaction(function () use ($order, $reason): void {
            // Mismo orden de bloqueo que los cobros y adiciones: mesa → sesión → pedido.
            $session = $order->table_session_id ? TableSessionLock::lockSession($order->table_session_id) : null;
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->isActive()) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se pueden cancelar pedidos PENDIENTES o POR COBRAR.'],
                ]);
            }

            $previousStatus = $locked->status;
            $locked->update(['status' => OrderStatus::CANCELLED]);
            $locked->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::CANCELLED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => 'Motivo de la cancelación: '.$reason,
            ]);

            if ($locked->type === OrderType::TABLE && TableSessionLock::isActive($session)
                && ! $session->orders()->whereIn('status', OrderStatus::activeValues())->exists()) {
                $session->update([
                    'status' => TableSessionStatus::CLOSED,
                    'ended_at' => now(),
                ]);
                $session->restaurantTable?->update([
                    'status' => TableStatus::AVAILABLE,
                ]);
            }
        });

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        $redirect = redirect()
            ->route($route, $route === 'admin.orders.show' ? $order : [])
            ->with('success', "Pedido #{$order->id} cancelado.");

        // Si cocina ya lo había recibido, hace falta la comanda "❌ PEDIDO CANCELADO — NO PREPARAR":
        // la imprime caja (ADMIN); al mesero se le avisa que caja debe imprimirla.
        if (! $order->hasSentKitchenTicket()) {
            return $redirect;
        }

        return $route === 'waiter.orders'
            ? $redirect->with('cancel_ticket_notice', 'Cocina ya tenía este pedido: caja debe imprimir la comanda de cancelación (❌ NO PREPARAR).')
            : $redirect->with('cancel_ticket', route('admin.orders.cancellation-ticket', $order));
    }
}
