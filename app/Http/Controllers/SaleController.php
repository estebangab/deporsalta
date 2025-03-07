<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateSaleRequest;
use App\Quantity;
use App\Sale;
use App\Productprice;
use App\Waist;
use App\Category;
use App\Product;
use App\SaleDetail;
use App\Payment;

class SaleController extends Controller
{
    public  function list()
    {
      $sales = Sale::where('status','1')->get();
      //dd($sales);
     
      $salesDetail = SaleDetail::with([
        'sale' => function($query){
         $query->get();
        }
        ])->paginate(10);  
        //dd($sales);  
      //])->where('status','=','0')->paginate(10);  
    return view('sale.list',[
      'sales' => $sales,
      'salesDetail' => $salesDetail
    ]);    
  }

    public function new(Product $product)//agrega nuevo item a venta actual
    {
      $sale = Sale::where('status','0')->count();

      $quantities = Quantity::with([
        'waist' => function($query){
         $query->get();
        }
        ])->join('productprices', function($join)
          {
            $join->on('productprices.product_id','=','quantities.product_id');
            $join->on('productprices.waist_id','=','quantities.waist_id');
          })
      ->where('quantities.product_id',$product->id)->get();     
      if ($sale > 0) {
        $sale = Sale::where('status','0')->first();        
      } else {
        $sale = Sale::create([
          'date' => date('Y-m-d'),
          'client_id' => '1',
          'user_id' =>'1',
          'status' =>'0'          
        ]);        
      }           
      $productImage = Product::with([
        'picture' => function($query){
          $query->first();
        }
      ])->find($product->id);
      
      $category = Category::find($product->category_id);
      $waists =Waist::where('type',$category->type)->get();
      return view('sale.create',[
        'product' =>$product,
        'waists' =>$waists,
        'category' => $category,
        'sale' => $sale,
        'error'=>'',
        'quantities' => $quantities,
        'productImage' => $productImage
        ]);
    }

    public function create(CreateSaleRequest $request)
    {
      $saleDetail = SaleDetail::create([
        'sale_id' => $request->sale_id,
        'product_id' => $request->product_id,
        'waist_id' => $request->waist_id,
        'quantity' => $request->quantity,
        'priceUnit' => $request->priceUnit,
        'total' => $request->total,
        'status' => '0'
      ]);

      //dd($saleDetail);
      // $user = $request->user();
  
      // $quant = Quantity::where('product_id','=',$request->input('product_id'))->Where('waist_id','=',$request->input('waist_id'))->first();

      // if (!is_null($quant)) {
      //   if ($quant->quantity >= $request->input('quantity')) {
      //     $sale = Sale::create([
      //       'product_id' =>$request->input('product_id'),
      //       'user_id' => $user->id,
      //       'waist_id' =>$request->input('waist_id'),
      //       'quantity' =>$request->input('quantity'),
      //       'priceUnit' =>$request->input('priceUnit'),
      //       'total' =>$request->input('total')
      //     ]);       
          
      //     $quantUpdate = Quantity::where('product_id','=',$request->input('product_id'))
      //           ->Where('waist_id','=',$request->input('waist_id'))
      //           ->update(['quantity' => $quant->quantity-$request->input('quantity')]);
                        
      //     return redirect('/products'); 
      //   }else{
      //     $product = Product::where('id','=',$request->input('product_id'))->first();
      //     $waists =Waist::all();
      //     return view('sale.create',[
      //         'product'=>$product,
      //         'waists' =>$waists,
      //         'error'=>'No existe stock suficiente para realizar esta venta'
      //         ]);  
      //   }
      // } else {
      //   $product = Product::where('id','=',$request->input('product_id'))->first();
      //     $waists =Waist::all();
      //     return view('sale.create',[
      //         'product'=>$product,
      //         'waists' =>$waists,
      //         'error'=>'No existe stock suficiente para realizar esta venta'
      //         ]);     
      // }
      
        return redirect('productsale');
    }

    public function delete($id)
    {
        //$saleDetail = ;

        // $quant = Quantity::where('product_id','=',$sale->product_id)->Where('waist_id','=',$sale->waist_id)->first();

        // $quantUpdate = Quantity::where('product_id','=',$sale->product_id)
        // ->Where('waist_id','=',$sale->waist_id)
        // ->update(['quantity' => $quant->quantity + $sale->quantity]);

        // $sale->status = 1;
        // $sale->save();

        return response()->json(SaleDetail::destroy($id));
    }

    public function sumTotal($id)
    {
       $total = SaleDetail::where('sale_id',$id)->sum('total');

        return response()->json($total);
    }

    public function confirm($id)
    {
       $sale = Sale::find($id);
       $sale->status = '1';
       $sale->save();

        return response()->json($sale);
    }


    public function priceUnit(Request $request)
    {
       $price = Productprice::join('quantities', function($join)
       {
         $join->on('quantities.product_id','=','productprices.product_id');
         $join->on('quantities.waist_id','=','productprices.waist_id');
        })
       ->where('productprices.product_id',$request->product_id)->where('productprices.waist_id',$request->waist_id)->first();       
       return response()->json($price);
    }


    public function confirmPost(Request $request)
    {
       $sale = Sale::find($request->sale_id);
       $sale->status = '1';
       $sale->save();

       $details = SaleDetail::where('sale_id',$request->sale_id)->get();

       foreach( $details as $item)
       {
          $quantity = Quantity::where('product_id',$item->product_id)->where('waist_id',$item->waist_id)->first();

          $quantity->quantity = $quantity->quantity - $item->quantity;
          Quantity::where('product_id',$item->product_id)->where('waist_id',$item->waist_id)->update(['quantity' => $quantity->quantity]);
          //$quantity->save();
        }
       //dd($quantity);
       if ($request->montoEfectivo !='0') {
          $payment = Payment::create([
            'sale_id' => $request->sale_id,
            'type' => 'efectivo',
            'amount' => $request->montoEfectivo
          ]);
       }

       if ($request->montoTarjeta !='0') {
        $payment = Payment::create([
          'sale_id' => $request->sale_id,
          'type' => $request->tipoTarjeta,
          'amount' => $request->montoTarjeta
        ]);
     }
       
        return response()->json($sale);
    }

    public function addItem(Product $product, Waist $waist)      
    {
      $price = Productprice::where('product_id',$product->id)
                            ->where('waist_id',$waist->id)
                            ->get()->first();
                               
        
        // $sale = Sale::findOrFail($id);

        // $quant = Quantity::where('product_id','=',$sale->product_id)->Where('waist_id','=',$sale->waist_id)->first();

        // $quantUpdate = Quantity::where('product_id','=',$sale->product_id)
        // ->Where('waist_id','=',$sale->waist_id)
        // ->update(['quantity' => $quant->quantity + $sale->quantity]);

        // $sale->status = 1;
        // $sale->save();
        //dd($price);
        return response()->json($price);
    }
  
  
}
