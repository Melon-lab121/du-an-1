<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;

final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home', [
            'featured' => Product::active()->where('is_featured', 1)->latest()->limit(8)->get(),
            'latest' => Product::active()->latest()->limit(8)->get(),
            'categories' => Category::withProductCount(),
        ]);
    }
}
