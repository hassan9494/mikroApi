<?php

namespace App\Http\Controllers;

use App\Models\OldCategory;
use App\Models\OldCourse;
use App\Models\OldGraduationProject;
use App\Models\OldKitInclude;
use App\Models\OldOrder;
use App\Models\OldOutlay;
use App\Models\OldProduct;
use App\Models\OldProductCategory;
use App\Models\OldProductRelated;
use App\Models\OldSupplier;
use App\Models\OldUser;
use App\Models\User;
use App\Traits\Media;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Common\Entities\Course;
use Modules\Common\Entities\GraduationProject;
use Modules\Common\Entities\Outlay;
use Modules\Shop\Entities\Category;
use Modules\Shop\Entities\Kit;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\OrderProduct;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductCategory;
use Modules\Shop\Entities\ProductRelated;
use Modules\Shop\Entities\ShippingProvider;
use Modules\Shop\Entities\Supplier;
use Modules\Shop\Http\Resources\ProductResource;

use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class ImportController extends Controller
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $repository;

    /**
     * ProductController constructor.
     * @param ProductRepositoryInterface $repository
     */
    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function category()
    {
        $oldCategory = OldCategory::all();
        foreach ($oldCategory as $item) {
            $category = new Category();
            $category->id = $item->id;
            $category->title = $item->name;
            $category->order = $item->order == -1 ? null : $item->order;
            $category->slug = $item->link;
            $category->icon = 'test';
            $category->save();
        }
    }

    public function product()
    {
        $oldProducts = OldProduct::where('id','>',12161)->get();
//        dd($oldProducts);
        foreach ($oldProducts as $key => $product) {
            $newProduct = Product::find($product->id);
            if ($newProduct==null) {
                $newProduct = new Product();
                $newProduct->id = $product->id;
            }

            $newProduct->name = $product->name;
            $newProduct->slug = Str::slug($product->name, '-');
            $newProduct->sku = 'me-' . $product->id;
            $newProduct->source_sku = '';
            $newProduct->gallery = $product->images;
            $newProduct->short_description = Str::limit(strip_tags($product->Description), 400);
            $newProduct->description = $product->Description;
            $newProduct->features = $product->Feature;
            $newProduct->documents = $product->Documents;
            $newProduct->code = $product->MikroCode;
            $newProduct->packageInclude = $product->KitInclude;
            $newProduct->datasheets = null;
            $newProduct->shipping = null;
            $meta['title'] = $product->seo_tags;
            $meta['keywords'] = $product->tags;
            $meta['description'] = $product->seo_tags;
            $newProduct->meta = $meta;
            $newProduct->stock = $product->qty;
            $newProduct->location = $product->location;
            $newProduct->ListPriority = $product->ListPriority;
            $newProduct->maxCartAmount = $product->maxCartAmount ?? 0;
            $prices = json_decode($product->price);
            foreach ($prices->prices as $old_price) {
                if ($old_price->LargerThanQty == 1) {
                    $price['normal_price'] = $old_price->NormalPrice;
                    $price['sale_price'] = $old_price->SalePrice;
                }
            }
            $price['real_price'] = $product->real_price;
            $price['distributor_price'] = $product->DistributorSale;
            $newProduct->price = $price;
            $option['available'] = true;
            $option['kit'] = $product->IsKit == 0 ? false : true;
            $option['featured'] = false;
            $newProduct->options = $option;
            $newProduct->save();
        }
    }

    public function importProductImages()
    {

        $oldProducts = Product::where('id','>',12161)->get();
//        dd($oldProducts);
        foreach ($oldProducts as $product) {

            $media = [];

            $test = str_replace('[', '', $product->gallery);
            $test2 = str_replace(']', '', $test);
            foreach (explode(',', $test2) as $key => $item) {
                $oldmedia = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('file_name',str_replace('"', '', $item))->first();
                if ($item != '' ){
                    if ( $oldmedia != null){
                        $media[$key] = [
                            'id' => $oldmedia->id, 'key' => "temp/" . str_replace('"', '', $item), 'url' => "/storage/temp/" . str_replace('"', '', $item)
                        ];
                    }else{

                        $media[$key] = [
                            'id' => $item, 'key' => "temp/" . str_replace('"', '', $item), 'new' => true, 'url' => "/storage/temp/" . str_replace('"', '', $item)
                        ];
                    }
                }



            }
            $x['media'] = $media;
            foreach ($media as $file) {
                if (Storage::exists($file['key'])) {
                    $product->syncMedia([$file]);
                }
            }
        }
        return 'hi';
    }

    public function updateProductsQty()
    {
// Assuming you have uploaded the file and it's now in the 'public' disk
        $filePath = 'qty.csv';

// Read the file content
        $fileContent = Storage::disk('public')->get($filePath);

// Convert content into an array based on new lines
        $lines = explode(PHP_EOL, $fileContent);
        foreach ($lines as $line) {
            // Skip the header line or empty lines
            if (!empty($line) && !str_contains($line, 'id,qty')) {
                // Convert each line into an array based on commas
                $data = str_getcsv($line);

                // Find the product by ID and update the qty
                $product = Product::find($data[0]);
                if ($product) {
                    $product->stock = $data[1];
                    $product->save();
                }
            }
        }
    }

    public function productCategory()
    {
        $oldProductCategory = OldProductCategory::all();
        foreach ($oldProductCategory as $item) {
            $newProductCategory = new ProductCategory();
            $newProductCategory->product_id = $item->product_id;
            $newProductCategory->category_id = $item->category_id;
            $newProductCategory->save();
        }
    }

    public function productRelated()
    {
        $oldProductRelated = OldProductRelated::all();
        foreach ($oldProductRelated as $item) {
            $newProductRelated = new ProductRelated();
            $parent =Product::find($item->product1_id);
            $child =Product::find($item->product2_id);
            if ($parent != null && $child != null) {
                $newProductRelated->parent_id = $item->product1_id;
                $newProductRelated->child_id = $item->product2_id;
                $newProductRelated->save();
            }else{
//                dd($item->product1_id , $item->product2_id);
            }

        }
    }

    public function kitInclude()
    {
        $oldKitIncludes = OldKitInclude::all();
        foreach ($oldKitIncludes as $item) {
            $parent =Product::find($item->kit_id);
            $child =Product::find($item->item_id);
            if ($parent != null && $child != null) {
            $newKitIncludes = new Kit();
            $newKitIncludes->kit_id = $item->kit_id;
            $newKitIncludes->product_id = $item->item_id;
            $newKitIncludes->quantity = $item->qty;
            $newKitIncludes->save();
            }
        }
    }

    public function courses()
    {
        $oldCourses = OldCourse::all();
        foreach ($oldCourses as $item) {
            $course = new Course();
            $course->name = $item->name;
            $course->cost = $item->cost;
            $course->start_at = $item->createDate;
            $course->end_at = $item->FinishDate;
            $course->description = '';
            $course->save();


        }
    }

    public function graduationProject()
    {
        $oldProjects = OldGraduationProject::all();
        foreach ($oldProjects as $item) {
            $newProject = new GraduationProject();
            $newProject->name = $item->name;
            $newProject->cost = $item->amount;
            $newProject->deadline = $item->deadline;
            $newProject->description = $item->description;
            $newProject->students = $item->studentsName;
            $newProject->notes = $item->paymentsDetails;
            $newProject->created_at = $item->createDate;
            $newProject->completed = 1;
            $newProject->save();

        }
    }

    public function outlay()
    {
        $oldOutlays = OldOutlay::all();
        foreach ($oldOutlays as $item) {
            $newOutlay = new Outlay();
            $newOutlay->name = $item->name;
            $newOutlay->invoice = $item->invoiceReference;
            $newOutlay->amount = $item->amount;
            $newOutlay->date = $item->date;
            $newOutlay->notes = '';
            if ($item->type == 1) {
                $newOutlay->type = 'PURCHASE';
                $newOutlay->sub_type = 'TAX';
            } else {
                $newOutlay->type = 'OUTLAY';
                $newOutlay->sub_type = 'ADMINISTRATIVE';
            }

            $newOutlay->total_amount = 0;
            $newOutlay->tax = null;
            $newOutlay->save();
        }
    }

    public function supplier()
    {
        $oldSuppliers = OldSupplier::all();
        foreach ($oldSuppliers as $item) {
            $supplier = new Supplier();
            $shippingProvider = new ShippingProvider();
            $supplier->name = $item->name;
            $supplier->id = $item->id;
            $shippingProvider->name = $item->name;
            $shippingProvider->id = $item->id;
            $supplier->phone = $item->phone;
            $shippingProvider->phone = $item->phone;
            $supplier->email = $item->email;
            $shippingProvider->email = $item->email;
            $supplier->university = $item->UniversityName;
            $supplier->address = $item->address;
            $supplier->notes = $item->note;
            $shippingProvider->notes = $item->note;
            $supplier->supplier_percentage = $item->SupplierPercent;
            $supplier->show_as_shipping_method = $item->IsShowAsShippingMethod;
            $supplier->user_id = null;
            if ($item->deleted == 1) {
                $supplier->deleted_at = Carbon::now();
                $shippingProvider->deleted_at = Carbon::now();
            }
            $supplier->save();
            $shippingProvider->save();

        }
    }

    public function user()
    {
        $oldUsers = OldUser::all();
        foreach ($oldUsers as $item) {
            $user = new User();
            $user->id = $item->id;
            $user->name = $item->user_name;
            if ($item->email != null) {
                $user->email = $item->email;
            } else {
                $user->email = $item->user_name . '@mikroelectron.com';
            }

            $user->password = Hash::make($item->password);
            $user->phone = $item->phone_number;
            $user->status = 1;
            $user->email_verified_at = Carbon::now();
            $user->save();

        }
    }

    public function order()
    {
        $oldOrders = OldOrder::where('id', '>', 74091)->get();
        foreach ($oldOrders as $item) {
            $newOrder = new Order();
            $newOrder->id = $item->id;
            if ($item->user_id != null) {
                $user = User::where('name', $item->user_id)->first();
                if ($user) {
                    $newOrder->user_id = $user->id;
                } else {
                    $newOrder->user_id = null;
                }
            } else {
                $newOrder->user_id = null;
            }
//            if ($item->user_id != null){
//                $user = User::where('name',$item->user_id)->first();
//                if ($user){
//                    $newOrder->user_id  = $user->id;
//                }else{
//                    $newOrder->user_id  = null;
//                }
//            }else{
//                $newOrder->user_id  = null;
//            }
            $newOrder->shipping_provider_id = $item->AssignTo;
            $newOrder->city_id = null;
            $newOrder->coupon_id = null;
            if ($item->TaxStatus == 'Rebate') {
                $newOrder->status = 'CANCELED';
            } else {
                $newOrder->status = $item->status;
            }

            $customer['name'] = $item->name;
            $customer['phone'] = $item->phone_number;
            $customer['email'] = $item->email;
            $newOrder->customer = $customer;

            $shipping['address'] = $item->shipping_address;
            $shipping['cost'] = $item->shipping_cost;
            $shipping['status'] = "DELIVERED";
            $shipping['free'] = false;
            $newOrder->shipping = $shipping;

            $options['taxed'] = $item->TaxStatus == 'TaxPayment' ? true : false;
            $options['tax_exempt'] = $item->isForceNoTax == 0 ? false : true;
            $options['dept'] = $item->isReceivables == 0 ? false : true;
            $options['price_offer'] = $item->status == 'Active' ? true : false;
            $options['pricing'] = false;
            $options['tax_zero'] = $item->isForceNoTax == 0 ? false : true;
            $newOrder->options = $options;
            $newOrder->tax_number = $item->TaxNumber;
            $newOrder->subtotal = $item->SubTotalPrice;
            $newOrder->discount = $item->discount;
            $newOrder->total = $item->TotalRealPrice;
            $newOrder->profit = null;
            $newOrder->notes = $item->order_notes;
            $newOrder->invoice_notes = $item->invoiceNote;
            $newOrder->taxed_at = $item->create_date;
            $newOrder->completed_at = $item->comlete_date;

            $newOrder->created_at = $item->create_date;
            $newOrder->updated_at = $item->create_date;
//            dd(json_decode($item->items)->items);
            $newOrder->save();
            $extraItems = [];
            if (json_decode($item->items) != null) {
                foreach (json_decode($item->items)->items as $orderItem) {
                    $product = Product::find($orderItem->productID);
                    if ($product != null) {
                        $orderProduct = new OrderProduct();
                        $orderProduct->order_id = $item->id;
                        $orderProduct->product_id = $orderItem->productID;
                        $orderProduct->price = $orderItem->unitePrice;
                        $orderProduct->real_price = $orderItem->realPrice;
                        $orderProduct->quantity = $orderItem->QTY;
                        $orderProduct->save();
                    } else {
                        $extraItem['name'] = $orderItem->productName;
                        $extraItem['price'] = $orderItem->realPrice;
                        $extraItem['quantity'] = $orderItem->QTY;

                        array_push($extraItems, $extraItem);
                    }

                }
                $newOrder->extra_items = $extraItems;
                $newOrder->save();
            }

        }
    }
}
