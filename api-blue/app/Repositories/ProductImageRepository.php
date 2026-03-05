<?php

namespace App\Repositories;

use App\Interfaces\ProductImageRepositoryInterface;
use App\Models\ProductImage;
use exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageRepository implements ProductImageRepositoryInterface
{
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $productImage = new ProductImage();
            $productImage->product_id = $data['product_id'];
            // Save raw file immediately for fast API response
            $rawPath = $data['image']->store('assets/products', 'public');
            $productImage->image = $rawPath;
            $productImage->is_thumbnail = $data['is_thumbnail'];
            $productImage->save();

            DB::commit();

            // Dispatch async job for resize + WebP conversion
            \App\Jobs\ProcessProductImageJob::dispatch(
                $productImage->id,
                $rawPath
            );

            return $productImage;
        } catch (Exception $e) {
            DB::rollBack();
            throw new exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $productImage = ProductImage::find($id);
            // Delete the image file from storage
            Storage::disk('public')->delete($productImage->image);
            // Delete the database record
            $productImage->delete();

            DB::commit();

            return $productImage;
        } catch (Exception $e) {
            DB::rollBack();
            throw new exception($e->getMessage());
        }
    }
}