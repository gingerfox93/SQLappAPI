<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use DB;

use Illuminate\Routing\Controller as BaseController;

class UserController extends BaseController
{
    public function login(Request $request)
    {

        $email = $request->input('email');
        $password = $request->input('password');
        
        $result = DB::select('select id,email,password, enabled from user where email = ?', [$email]);
    
        if(count($result) > 0) {
            if($result[0]->password == $password && $result[0]->enabled == 1){
            $result = [
                'status' => 'userexists',
                'userId' => $result[0]->id,
                'enabled' => 1,
            ];
        } else {
             $result = [
                'status' => 'usernotfound',
                'userId' => 0,
                'enabled' => 0,
            ]; 
        }
        }
        
                      
        return \Response::json($result,200);

    }   
    
    public function register(Request $request)
    {

        $email = $request->input('email');
        $password = $request->input('password');
        
        $result = DB::select('select email from user where email = ?', [$email]);
        
        if(!isset($result[0]->email)){
        
            $result = DB::insert('insert into user (email, password, enabled) values (?, ?, ?)', array($request->input('email'),$request->input('password') , 1));
            
            $result = DB::select('select id from user where email = ?', [$email]);
            
            $response = [
                'response' => 'User created',
                'status' => 1
            ];
       
        } else {
            $response = [
                'response' => 'User already exists',
                'status' => 0
            ];
        }
       
            
       
                      
        return \Response::json($response,200);

    }   
    
    
}
