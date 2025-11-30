<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function show($id)
    {
       $product = Product::findOrFail($id);
       return response()->json([

        'id' => $product->id,
        'name' => $product->name,
        'price' => $product->price,
        'available_stock' => $product->available_stock,
       ]);
    }
}
