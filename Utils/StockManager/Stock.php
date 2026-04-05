<?php

namespace App\Utils\StockManager;

use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\ProductVariantStockLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Stock
{
    // public static function update(
    //     ProductVariant $variant,
    //     int $amount,
    //     ?Branch $toBranch = null,
    //     ?Branch $fromBranch = null,
    // ): void {
    //     $stockUpdater = new StockUpdater($variant, $amount, $toBranch, $fromBranch);
    //     $stockUpdater->dispatch();
    // }

    /**
     * v2 Usage:
     * new Stock($variant)->add($amount)->to($toBranch)->reference($purchase);
     * new Stock($variant)->sub($amount)->from($fromBranch)->reference($sale);
     * new Stock($variant)->move($amount)->from($fromBranch)->to($toBranch)->reference($moveOrder);
     * new Stock($variant)->get($branch);
     */
    // public function __construct(private readonly ProductVariant $variant) {}

    public static function add(ProductVariant $variant, int $amount, Branch $toBranch, ?Model $form = null): void
    {
        new StockUpdater($variant, $amount, $toBranch, null, $form)->dispatch();
    }

    public static function sub(ProductVariant $variant, int $amount, Branch $fromBranch, ?Model $form = null): void
    {
        new StockUpdater($variant, $amount, null, $fromBranch, $form)->dispatch();
    }

    public static function move(ProductVariant $variant, int $amount, Branch $fromBranch, Branch $toBranch, ?Model $form = null): void
    {
        new StockUpdater($variant, $amount, $toBranch, $fromBranch, $form)->dispatch();
    }

    public static function get(Branch $branch, ProductVariant $productVariant): int
    {
        return ProductVariantStock::query()
            ->where('product_variant_id', $productVariant->id)
            ->where('branch_id', $branch->id)
            ->first()->stock ?? 0;
    }
}

class StockUpdater
{
    public function __construct(
        private ProductVariant $variant,
        private int $amount,
        private ?Branch $toBranch = null,
        private ?Branch $fromBranch = null,
        private ?Model $form = null,
    ) {
        throw_if(
            empty($fromBranch) && empty($toBranch),
            new \InvalidArgumentException('Either fromBranch or toBranch must be provided.'),
        );

        throw_if($this->amount === 0, new \InvalidArgumentException('Amount must be non-zero.'));
    }

    /**
     * Get the action string containing route controller name and parameters.
     */
    private string $action {
        get {
            $route = request()->route();

            $actionName = $route?->getActionName() ?? ''; // e.g. App\Http\Controllers\ProductController@update
            $paramString = implode(',', array_map(fn ($o) => $o->id, array_values($route?->parameters() ?? [])));

            // e.g App\Http\Controllers\ProductController@update:1
            return $paramString ? "{$actionName}:{$paramString}" : $actionName;
        }
    }

    public function dispatch(): void
    {
        // $this->getStock();

        try {
            $this->updateStock();
            $this->logStock();
        } catch (\Illuminate\Database\QueryException $th) {
            if (str()->contains($th->getMessage(), 'Numeric value out of range')) {
                $branchOrigin = $this->fromBranch?->code;
                $amount = $this->amount;
                $currentStock = $this->fromBranch ? Stock::get($this->fromBranch, $this->variant) : 0;
                throw new \InvalidArgumentException("[$branchOrigin] Insufficient stock. Variant ID: {$this->variant->id}, code: {$this->variant->code()}, amount: {$amount}, current stock: {$currentStock}.");
            }
            throw $th;
        }
    }

    private function updateStock(): void
    {
        if ($this->fromBranch) {
            ProductVariantStock::updateOrInsert(
                [
                    'product_variant_id' => $this->variant->id,
                    'branch_id' => $this->fromBranch->id,
                ],
                [
                    'stock' => DB::raw('stock - '.$this->amount),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            // delete stock record if stock becomes 0
            // ProductVariantStock::where('stock', 0)->delete();
        }

        if ($this->toBranch) {
            ProductVariantStock::updateOrInsert(
                [
                    'product_variant_id' => $this->variant->id,
                    'branch_id' => $this->toBranch->id,
                ],
                [
                    'stock' => DB::raw('COALESCE(stock, 0) + '.$this->amount),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function logStock(): void
    {
        $actorId = auth()->id();

        /** @var int|null */
        $formId = $this->form?->id ?? null;
        /** @var string|null */
        $formType = $this->form ? $this->form::class : null;

        if ($this->fromBranch) {
            ProductVariantStockLog::create([
                'product_variant_id' => $this->variant->id,
                'branch_id' => $this->fromBranch->id,
                'amount' => -$this->amount,
                'action' => $this->action,
                'actor_id' => $actorId,
                'form_id' => $formId,
                'form_type' => $formType,
                'effective_at' => now(),
            ]);
        }

        if ($this->toBranch) {
            ProductVariantStockLog::create([
                'product_variant_id' => $this->variant->id,
                'branch_id' => $this->toBranch->id,
                'amount' => $this->amount,
                'action' => $this->action,
                'actor_id' => $actorId,
                'form_id' => $formId,
                'form_type' => $formType,
                'effective_at' => now(),
            ]);
        }
    }
}
