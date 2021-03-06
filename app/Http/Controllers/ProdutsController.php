<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Http\Requests;
use App\Produt;
use App\product_img;
use App\Basket_Temp;
use App\products_purchased;
use Illuminate\Support\Facades\Storage;
//use Illuminate\Http\File;
//use Illuminate\Contracts\Filesystem\Filesystem;
use Auth;
use App\User;

class ProdutsController extends Controller
{
    /*public function products()
    {
    	$produts = DB::table('produts')->get();
        return view('produts', compact('produts'));
    }*/

    public function addBasketTemp(Request $request, Produt $produt){

        if (Auth::check()){
            $currentUser = Auth::user();
            $basket_temp = new Basket_Temp();
            $basket_temp->product_id = $produt->id;
            $currentUser->basket_temp()->save($basket_temp);
            return redirect("/products/all");
        }
        else{
            return redirect("/login");
        }
    }

    public function addProduct(Request $request, User $user)
    {
        if($user->type) {
            $produt = new Produt();
            $produt->name = $request->productName;
            $produt->price = $request->productPrice;
            if($produt->save()){
                $image = $request->file('image');
                $imageFileName = time() . '.' . $image->getClientOriginalExtension();
                $filePath = '/products/' . $imageFileName;
                $s3 = \Storage::disk('s3');
                if($s3->put($filePath, file_get_contents($image), 'public')){
                    $product_img = new product_img();
                    $product_img->title = $imageFileName;
                    $product_img->path="products/";
                    $produt->product_img()->save($product_img);
                    return redirect("/user");
                }
            }

            return redirect("/user");
        }
    }
    //verifica o estado e dependente elimina ou acrescenta imagem

    public function delete_editProducts(Request $request)
    {
        $currentUser = Auth::user();
        $produts = Produt::all();
        if($request->productState== "Eliminar"){
            foreach ($produts as $produt) {
                $id = $produt->id;
                if ($currentUser->type) {
                    if ($request->product_Id == $id) {
                        DB::table('Basket_Temp')->where('product_id', '=', $id)->delete();
                        if(DB::table('produts')->where('id', '=', $id)->delete()) {

                            $product_imgs = DB::table('product_img')->where('product_id', '=', $id)->get();
                            foreach ($product_imgs as $product_img) {
                                $path = $product_img->path;
                                $title = $product_img->title;
                                \Storage::disk('s3')->delete($path . '' . $title);
                            }
                            DB::table('product_img')->where('product_id', '=', $id)->delete();
                        }
                    } else {
                        continue;
                    }
                } else {
                    return redirect("/user");
                }
            }
            return redirect("/user");
        }
        else{
            foreach ($produts as $produt) {
                $id = $produt->id;
                if ($currentUser->type) {
                    if ($request->product_Id == $id) {

                        $image = $request->file('image');
                        $imageFileName = time() . '.' . $image->getClientOriginalExtension();
                        $filePath = '/products/' . $imageFileName;
                        $s3 = \Storage::disk('s3');
                        if($s3->put($filePath, file_get_contents($image), 'public')){
                            $product_img = new product_img();
                            $product_img->title = $imageFileName;
                            $product_img->path="products/";
                            $produt->product_img()->save($product_img);
                            return redirect("/user");
                        }
                    } else {
                        continue;
                    }
                } else {
                    return redirect("/user");
                }
            }
            return redirect("/user");
        }
    }
    //eliminar ou  comprar os produtos do carrinho do sócio
    public function basketOperation(Request $request)
    {
        $basket_products = Basket_Temp::get();
        foreach ($basket_products as $basket_product) {
            $basket_id = $basket_product->basket_id;
            $currentUser = Auth::user();
            $id_user = $currentUser->id;
            $basket_product_id = $basket_product->product_id;
            $basket_ticket_id = $basket_product->ticket_id;
            if ($request->$basket_id) {
                if ($request->$basket_id == "Eliminar") {
                    DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                } elseif ($request->$basket_id == "Comprar") {
                    if ($basket_ticket_id == "") {
                        $currentUser = Auth::user();
                        $products = DB::table('produts')->where('id', '=', $basket_product_id)->get();
                        foreach ($products as $product) {
                            $price = $product->price;
                        }
                        $amount = $currentUser->amount;
                        $a_amount = $amount - $product->price;
                        DB::table('users')->where('id', '=', $currentUser->id)->update(array('amount' => $a_amount));
                        DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                        $products_purchased = new products_purchased();
                        $products_purchased->product_id = $basket_product_id;
                        $currentUser->basket()->save($products_purchased);
                        return redirect("/user");
                    } elseif ($basket_product_id == "") {
                        $currentUser = Auth::user();
                        $tickets = DB::table('tickets')->where('id', '=', $basket_ticket_id)->get();
                        foreach ($tickets as $ticket) {
                            $price = $ticket->price;
                        }
                        $amount = $currentUser->amount;
                        $a_amount = $amount - $ticket->price;
                        DB::table('users')->where('id', '=', $currentUser->id)->update(array('amount' => $a_amount));
                        DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                        $products_purchased = new products_purchased();
                        $products_purchased->ticket_id = $basket_ticket_id;
                        $currentUser->basket()->save($products_purchased);


                        //diminuir lugares disponíveis
                        $game_id = DB::table('tickets')->where('id', '=', $basket_ticket_id)->value('game_id');
                        $stadium_zone = DB::table('tickets')->where('id', '=', $basket_ticket_id)->value('area');
                        $stadium_id = DB::table('games')->where('game_id', '=', $game_id)->value('stadium_id');
                        $stadiums = DB::table('stadium_places')->where('stadium_id', '=', $stadium_id)->get();
                        foreach ($stadiums as $stadium) {
                            $area_places = $stadium->$stadium_zone;
                            $new_area_places = $area_places - 1;
                            DB::table('stadium_places')->where('stadium_id', '=', $stadium_id)->update(array($stadium_zone => $new_area_places));

                        }
                        return redirect("/user");
                    }
                }
            } elseif ($request->buyAll) {
                //comprar todos os produtos
                if ($basket_ticket_id == "") {
                    //$currentUser = Auth::user();
                    $amount = DB::table('users')->where('id', '=', $id_user)->value('amount');
                    $products = DB::table('produts')->where('id', '=', $basket_product_id)->get();
                    foreach ($products as $product) {
                        $price = $product->price;
                    }
                    $a_amount = $amount - $price;
                    //Check if there is enough money
                    if($a_amount>=0) {
                        DB::table('users')->where('id', '=', $currentUser->id)->update(array('amount' => $a_amount));
                        $productd = DB::table('produts')->where('id', '=', $basket_product_id)->get();
                        DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                        $products_purchased = new products_purchased();
                        $products_purchased->product_id = $basket_product_id;
                        $currentUser->basket()->save($products_purchased);
                    }
                    else
                    {
                        return redirect("/user");
                    }

                } elseif ($basket_product_id == "") {
                    $amount = DB::table('users')->where('id', '=', $id_user)->value('amount');
                    $tickets = DB::table('tickets')->where('id', '=', $basket_ticket_id)->get();
                    foreach ($tickets as $ticket) {
                        $price = $ticket->price;
                    }
                    $a_amount = $amount - $ticket->price;
                    //Check if there is enough money
                    if($a_amount>=0) {
                        DB::table('users')->where('id', '=', $currentUser->id)->update(array('amount' => $a_amount));
                        DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                        $products_purchased = new products_purchased();
                        $products_purchased->ticket_id = $basket_ticket_id;
                        $currentUser->basket()->save($products_purchased);


                        //diminuir lugares disponíveis
                        $game_id = DB::table('tickets')->where('id', '=', $basket_ticket_id)->value('game_id');
                        $stadium_zone = DB::table('tickets')->where('id', '=', $basket_ticket_id)->value('area');
                        $stadium_id = DB::table('games')->where('game_id', '=', $game_id)->value('stadium_id');
                        $stadiums = DB::table('stadium_places')->where('stadium_id', '=', $stadium_id)->get();
                        foreach ($stadiums as $stadium) {
                            $area_places = $stadium->$stadium_zone;
                            $new_area_places = $area_places - 1;
                            DB::table('stadium_places')->where('stadium_id', '=', $stadium_id)->update(array($stadium_zone => $new_area_places));

                        }
                    }
                    else{
                        return redirect("/user");
                    }
                }
                continue;
            }
            elseif ($request->deleteAll) {
                DB::table('Basket_Temp')->where('basket_id', '=', $basket_id)->delete();
                continue;
            } else {
                continue;
            }
            return redirect("/user");
        }
        return redirect("/user");
    }


    /*----------------------- JORGE ------------------------------*/
    public function getProducts($product_id){
        if($product_id == "all") {
            $products = DB::table('produts')
                ->get();

            $products_images = DB::table('produts')
                ->leftJoin('product_img', 'produts.id', '=', 'product_id')
                ->select('produts.name','produts.price','produts.id','produts.created_at', 'produts.updated_at' ,'product_img.title','product_img.path' )
                ->get();
        }
        else
        {
            $products = DB::table('produts')
                ->where('produts.id','=', $product_id)
                ->get();

            $products_images  = DB::table('produts')
                ->leftJoin('product_img', 'produts.id', '=', 'product_id')
                ->select('produts.name','produts.price','produts.id','produts.created_at', 'produts.updated_at' ,'product_img.title','product_img.path' )
                ->where('produts.id','=', $product_id)
                ->get();
        }


        /*---- Colocar o links das imagens num array ---*/
        $array_urls = array();

        foreach($products_images as $imageName) {

            $s3 = Storage::disk('s3');
            if(!empty($imageName->title) && !empty($imageName->path)) {
                $path = $imageName->path.$imageName->title;
                $exists = $s3->exists($path);


                if ($exists) {
                    $urlFile = $s3->url($path);

                    $array_urls [$imageName->id][] = $urlFile;


                }
            }
        }
        if($product_id == "all") {
        return view('produts', compact('products', 'array_urls'));
        }
        else
        {
            return view('detailsProduct', compact('products', 'array_urls'));
        }
    }



}
