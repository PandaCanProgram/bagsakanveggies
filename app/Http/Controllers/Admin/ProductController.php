<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Requests\Admin\UpdatePricesRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Folder on the public disk where product photos are stored.
     */
    protected const IMAGE_DIRECTORY = 'products';

    public function index(): View
    {
        $products = Product::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.products.index', [
            'products' => $products,
            'lastUpdatedAt' => $products->max('updated_at'),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'variants' => [
                    ['label' => '10 kg bag', 'price' => null],
                    ['label' => '1 kg', 'price' => null],
                ],
            ]),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $product = new Product([
            ...$request->productAttributes(),
            'sort_order' => (Product::max('sort_order') ?? -1) + 1,
        ]);

        $this->applyImage($request, $product);
        $product->save();

        return redirect()->route('admin.products.index')
            ->with('status', "{$product->name} is now live on the store.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', ['product' => $product]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->fill($request->productAttributes());

        $replacedImagePath = $this->applyImage($request, $product);
        $product->save();

        if ($replacedImagePath) {
            Storage::disk('public')->delete($replacedImagePath);
        }

        return redirect()->route('admin.products.index')
            ->with('status', "Saved changes to {$product->name}.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        return redirect()->route('admin.products.index')
            ->with('status', "{$product->name} was deleted and is no longer on the store.");
    }

    /**
     * Save every changed price from the price list in one go.
     */
    public function updatePrices(UpdatePricesRequest $request): RedirectResponse
    {
        /** @var array<int, array<int, string>> $prices */
        $prices = $request->validated('prices');

        $updatedCount = DB::transaction(function () use ($prices): int {
            $updatedCount = 0;

            foreach (Product::whereKey(array_keys($prices))->get() as $product) {
                $variants = $product->variants;

                foreach ($prices[$product->id] as $index => $price) {
                    if (isset($variants[$index])) {
                        $variants[$index]['price'] = (int) $price;
                    }
                }

                if ($variants !== $product->variants) {
                    $product->update(['variants' => $variants]);
                    $updatedCount++;
                }
            }

            return $updatedCount;
        });

        return redirect()->route('admin.products.index')->with('status', match ($updatedCount) {
            0 => 'No price changes to save.',
            1 => 'Updated prices for 1 veggie. The store shows them now.',
            default => "Updated prices for {$updatedCount} veggies. The store shows them now.",
        });
    }

    /**
     * Store a newly uploaded photo or clear a removed one, returning the path of any photo it replaces.
     */
    protected function applyImage(SaveProductRequest $request, Product $product): ?string
    {
        $previousPath = $product->image_path;

        if ($request->hasFile('image')) {
            $product->image_path = $request->file('image')->store(self::IMAGE_DIRECTORY, 'public');
        } elseif ($request->boolean('remove_image')) {
            $product->image_path = null;
        }

        return $previousPath !== $product->image_path ? $previousPath : null;
    }
}
