<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\BeverageOptions;
use App\Support\ComboOptions;
use App\Support\JuiceOptions;
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
    public function index(Request $request): View { $orders=Order::query()->with(['tableSession.restaurantTable','orderItems.product','handledBy'])->when($request->status,fn($q,$s)=>$q->where('status',$s),fn($q)=>$q->where('status','!=',OrderStatus::COMPLETED->value))->oldest()->get(); return view('admin.orders.index',['orders'=>$orders,'statuses'=>OrderStatus::operationalCases(),'selectedStatus'=>$request->status]); }
    public function pending(): JsonResponse { $orders=Order::query()->where('status',OrderStatus::PENDING->value)->with(['tableSession.restaurantTable','handledBy'])->oldest()->get(); return response()->json(['count'=>$orders->count(),'ids'=>$orders->pluck('id')->values(),'orders'=>$orders->map(fn(Order $o)=>['id'=>$o->id,'location'=>$o->type?->value==='PARA_LLEVAR'?'PARA LLEVAR':($o->type?->value==='DOMICILIO'?'DOMICILIO':'MESA '.($o->tableSession?->restaurantTable?->number??'—')),'time'=>$o->created_at?->format('H:i'),'responsible'=>$o->handledBy?->name??'Pedido QR'])->values()]); }
    public function create(): View { return view('admin.orders.create-v2',['products'=>Product::query()->with(['category','beverageOptions'])->where('is_available',true)->orderBy('name')->get(),'tables'=>RestaurantTable::query()->where('status','!=',TableStatus::CLEANING->value)->orderBy('number')->get(),'categories'=>Category::query()->orderBy('name')->get(),'orderTypes'=>OrderType::cases(),'comboBeverages'=>collect(ComboOptions::types())->mapWithKeys(fn($label,$type)=>[$type=>['label'=>$label,'flavors'=>ComboOptions::availableFlavors($type)]])->all(),'comboPrice'=>ComboOptions::PRICE]); }
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
                $session=TableSession::query()->where('restaurant_table_id',$table->id)->where('status',TableSessionStatus::Active->value)->latest('id')->first();
                if(!$session)$session=TableSession::create(['restaurant_table_id'=>$table->id,'status'=>TableSessionStatus::Active,'started_at'=>now()]);
                $table->update(['status'=>TableStatus::OCCUPIED]);
            }

            $order=Order::create(['table_session_id'=>$session?->id,'type'=>$type,'status'=>OrderStatus::PENDING,'subtotal'=>0,'packaging_fee'=>0,'delivery_fee'=>(int)($v['delivery_fee']??0),'tax'=>0,'total'=>0,'customer_name'=>$v['customer_name']??null,'customer_phone'=>$v['customer_phone']??null,'delivery_address'=>$v['delivery_address']??null,'delivery_reference'=>$v['delivery_reference']??null,'notes'=>$v['notes']??null,'handled_by_user_id'=>Auth::id()]);
            $subtotal=0;
            $packagingFee=0;

            foreach($v['items'] as $productId=>$quantity){
                $p=Product::query()->with(['category','beverageOptions'])->findOrFail($productId);
                if(!$p->is_available)throw ValidationException::withMessages(['items'=>["El producto '{$p->name}' no está disponible."]]);
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
                $order->orderItems()->create(['product_id'=>$p->id,'quantity'=>$quantity,'unit_price'=>$price,'total'=>$line,'notes'=>$notes]);
                $subtotal+=$line;
            }

            $order->update(['subtotal'=>$subtotal,'packaging_fee'=>$packagingFee,'tax'=>0,'total'=>$subtotal+$packagingFee]);
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
            'order' => $order->load(['tableSession.restaurantTable']),
            'products' => Product::query()
                ->with(['category', 'beverageOptions'])
                ->where('is_available', true)
                ->orderBy('name')
                ->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'comboBeverages' => collect(ComboOptions::types())
                ->mapWithKeys(fn ($label, $type) => [
                    $type => [
                        'label' => $label,
                        'flavors' => ComboOptions::availableFlavors($type),
                    ],
                ])
                ->all(),
        ]);
    }

    public function storeAddition(Request $request, Order $order): RedirectResponse
    {
        $this->ensureAdditionAllowed($order);

        $v = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:99'],
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
            $order = Order::query()->lockForUpdate()->with('orderItems')->findOrFail($order->id);

            $nextRound = ((int) $order->rounds()->max('number')) + 1;
            $round = $order->rounds()->create([
                'number' => $nextRound,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($v['items'] as $productId => $quantity) {
                $p = Product::query()->with(['category', 'beverageOptions'])->findOrFail($productId);

                if (! $p->is_available) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$p->name}' no está disponible."],
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

                $round->orderItems()->create([
                    'product_id' => $p->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total' => $price * $quantity,
                    'notes' => $notes,
                    'sent_at' => null,
                ]);
            }

            $order->load('orderItems.product');
            $subtotal = $order->orderItems->sum('total');
            $packagingFee = $order->orderItems->sum(
                fn ($item) => TakeawayPackaging::fee(
                    $item->product,
                    (int) $item->quantity,
                    $order->type->value
                )
            );

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

        return redirect()
            ->route('waiter.orders')
            ->with('success', "Adición agregada al pedido #{$order->id}. Quedó pendiente de impresión.");
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
            if (! $order->tableSession || $order->tableSession->status !== AppTableSessionStatus::Active) {
                throw ValidationException::withMessages([
                    'order' => ['La sesión de esta mesa ya está cerrada.'],
                ]);
            }

            if (! in_array($order->status, [
                OrderStatus::PENDING,
                OrderStatus::PREPARING,
                OrderStatus::DELIVERED,
            ], true)) {
                throw ValidationException::withMessages([
                    'order' => ['La mesa no está disponible para recibir una nueva adición.'],
                ]);
            }

            return;
        }

        if (! in_array($order->status, [OrderStatus::PENDING, OrderStatus::PREPARING], true)) {
            throw ValidationException::withMessages([
                'order' => ['Este pedido ya fue entregado y no admite nuevas adiciones.'],
            ]);
        }
    }

    public function show(Order $order): View {$order->load(['tableSession.restaurantTable','orderItems.product','statusHistories.changedBy','handledBy','deliveredBy','paidBy']);return view('admin.orders.show',['order'=>$order,'statuses'=>OrderStatus::operationalCases()]);}
    public function updateStatus(Request $request,Order $order): RedirectResponse {$v=$request->validate(['status'=>['required',Rule::enum(OrderStatus::class)]]);$new=OrderStatus::from($v['status']);if($order->status!==OrderStatus::PENDING||$new!==OrderStatus::PREPARING)throw ValidationException::withMessages(['status'=>['El cambio a EN PREPARACIÓN se realiza al imprimir las comandas del pedido.']]);DB::transaction(function()use($order,$new){$prev=$order->status;$order->update(['status'=>$new]);$order->statusHistories()->create(['previous_status'=>$prev->value,'new_status'=>$new->value,'changed_by_user_id'=>Auth::id(),'changed_at'=>now()]);});return redirect()->route('admin.orders.show',$order)->with('success','Estado del pedido actualizado exitosamente.');}
    public function deliver(Order $order): RedirectResponse {if($order->status!==OrderStatus::PREPARING)throw ValidationException::withMessages(['status'=>['El pedido debe estar EN PREPARACIÓN para poder entregarse.']]);$prev=$order->status;DB::transaction(function()use($order,$prev){$order->update(['status'=>OrderStatus::DELIVERED,'delivered_by_user_id'=>Auth::id(),'delivered_at'=>now()]);$order->statusHistories()->create(['previous_status'=>$prev->value,'new_status'=>OrderStatus::DELIVERED->value,'changed_by_user_id'=>Auth::id(),'changed_at'=>now()]);});$route=Auth::user()?->role?->value==='MESERO'?'waiter.orders':'admin.orders.show';return redirect()->route($route,$route==='admin.orders.show'?$order:[])->with('success','Pedido entregado exitosamente.');}
}
