<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Requests\Admin\UpdatePricesRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Folder on the public disk where product photos are stored.
     */
    protected const IMAGE_DIRECTORY = 'products';

    /**
     * The Veggies or Fruits list (?category=fruit).
     */
    public function index(Request $request): View
    {
        $category = $this->requestedCategory($request);
        $products = Product::where('category', $category)->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.products.index', [
            'category' => $category,
            'products' => $products,
            'lastUpdatedAt' => $products->max('updated_at'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'category' => $this->requestedCategory($request),
                'variants' => [
                    ['label' => '10 kg bag', 'price' => null],
                    ['label' => '1 kg', 'price' => null],
                ],
            ]),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        // New veggies go first: everything else moves down one place.
        $product = new Product([
            ...$request->productAttributes(),
            'sort_order' => 0,
        ]);

        $this->applyImage($request, $product);

        DB::transaction(function () use ($product) {
            Product::query()->increment('sort_order');
            $product->save();
        });

        return redirect(Product::adminListUrl($product->category))
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

        return redirect(Product::adminListUrl($product->category))
            ->with('status', "Saved changes to {$product->name}.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        return redirect(Product::adminListUrl($product->category))
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

        // Back to the list the prices were saved from, Veggies or Fruits.
        $category = $request->validated('category') ?? Product::CATEGORY_VEGETABLE;
        $name = Product::ADMIN_NAMES[$category];

        return redirect(Product::adminListUrl($category))->with('status', match ($updatedCount) {
            0 => 'No price changes to save.',
            1 => "Updated prices for 1 {$name}. The store shows them now.",
            default => "Updated prices for {$updatedCount} ".Str::plural($name).'. The store shows them now.',
        });
    }

    /**
     * The category picked with ?category=fruit, Veggies by default. Anything else is not a page.
     */
    protected function requestedCategory(Request $request): string
    {
        $category = $request->query('category', Product::CATEGORY_VEGETABLE);

        abort_unless(is_string($category) && array_key_exists($category, Product::CATEGORIES), 404);

        return $category;
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
