<?php

namespace App\Repositories;

use App\Interfaces\TransactionDetailRepositoryInterface;
use App\Models\Product;
use App\Models\TransactionDetail;
use Exception;
use Illuminate\Support\Facades\DB;

class TransactionDetailRepository implements TransactionDetailRepositoryInterface
{
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $transactionDetail = new TransactionDetail;
            $transactionDetail->transaction_id = $data['transaction_id'];
            $transactionDetail->product_id = $data['product_id'];
            $transactionDetail->variant_id = $data['variant_id'] ?? null;
            $transactionDetail->qty = $data['qty'];

            // unit_price diteruskan dari TransactionRepository, sudah
            // resolved dari varian yang dibeli (lewat resolveVariant()) --
            // JANGAN derive ulang dari $product->price di sini, itu selalu
            // harga varian termurah kalau produknya punya varian.
            $unitPrice = $data['unit_price'] ?? Product::find($data['product_id'])?->price ?? 0;
            $transactionDetail->subtotal = $unitPrice * $data['qty'];

            $transactionDetail->save();

            DB::commit();

            return $transactionDetail;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
