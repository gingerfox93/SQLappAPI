<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use DB;

use Illuminate\Routing\Controller as BaseController;

class UserController extends BaseController
{   

    public function checkIn(Request $request)

    {

        $userId = $request->input('userId');
        $placeId = $request->input('placeId');

        //$userId = 1;

        //$placeId = 'ChIJe-RgsntxekgRHAq2nyYm70s';

        $dbResult = DB::select('DELETE FROM user_places_checked_in WHERE place_id = ? AND user_id = ?', [$placeId,$userId]);

        $dbResult =  DB::insert(
            'insert into user_places_checked_in (user_id,place_id,dateExpires) 
            values (?,?,NOW() + INTERVAL 5 HOUR)', 
            array($userId, $placeId)
            );


        if(count($dbResult) == 0){
            echo 'lol';
        }

        //check if currently checked in
        //SELECT COUNT(1) FROM user_places_checked_in WHERE place_id = ? AND user_id = ?
        
    }

    public function nearby(Request $request)

    {

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $userId = $request->input('user_id');
        $radius = $request->input('radius');
        $location = $lat . ',' . $lng;

        $userId = 1;
        
        $type = 'night_club|bar';
        $apiKey = 'AIzaSyAZCZvl191DIEqcV6p228UHtbu-3mdFL-w';

        $nearbyPlacesUrl = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location='.$location.'&radius='.$radius.'&type='.$type.'&key=' . $apiKey;
        
        $client = new Client();
        $res = $client->request('GET', $nearbyPlacesUrl);

        $result = json_decode($res->getBody());
        $result = $result->results;


        $output = [];

        foreach ($result as $key => $place) {
            
            $place_id = $result[$key]->place_id;
            $name = $result[$key]->name;
            $vicinity = $result[$key]->vicinity;
            $rating = (isset($result[$key]->rating) ? $result[$key]->rating : 0);
            $lat = $result[$key]->geometry->location->lat;
            $lng = $result[$key]->geometry->location->lng;

            $types = $result[$key]->types;
            $typeStr = '';
            foreach ($types as $type) {$typeStr = $typeStr . ',' . $type;}
            $typeStr = ltrim($typeStr, ',');

            //Attempt to fetch place details from database
            $dbResult = DB::select('SELECT COALESCE(active,0) as active FROM places WHERE place_id = ?', [$place_id]);

            if(count($dbResult) == 0){
                //Insert place into database if it doesnt exist
                DB::insert('insert into places (place_id,name,vicinity,rating,lat,lng,types) values (?,?,?,?,?,?,?)', array($place_id,$name,$vicinity,$rating,$lat,$lng,$typeStr));

            } else {
                //Update API count
                DB::insert('UPDATE places SET apiCount = apiCount + 1 WHERE place_id = ?', array($place_id));
            }

            //Remove place from results if not active in database
            if(isset($dbResult[0]) && $dbResult[0]->active == 1){
                array_push($output, $place);

                //Set color of map marker
                $checkedInResult = DB::select('SELECT COUNT(*) as checkedInCount FROM user_places_checked_in WHERE place_id = ?', [$place_id]);

                $result[$key]->checkedInCount = $checkedInResult[0]->checkedInCount;

                if($checkedInResult[0]->checkedInCount == 0){
                    $result[$key]->color = 'Blue';

                } 

                if($checkedInResult[0]->checkedInCount >= 1 && $checkedInResult[0]->checkedInCount < 4){
                    $result[$key]->color = 'Amber';
                } 

                if($checkedInResult[0]->checkedInCount >= 4 ){
                    $result[$key]->color = 'Green';
                } 

            } 

            if(!isset($dbResult[0])){
                array_push($output, $place);
            }
        }
        return \Response::json($output,200);

        // header('Content-Type: application/json');
        // echo json_encode($output);
    }

    public function fetchPlaceDetails(Request $request)

    {

        $placeId = $request->input('placeId');
        
        $googleMapsApiURL = 'https://maps.googleapis.com/maps/api/place/details/json?placeid='.$placeId.'&key=AIzaSyAZCZvl191DIEqcV6p228UHtbu-3mdFL-w';

        $client = new Client();
        $res = $client->request('GET', $googleMapsApiURL);

        $place = json_decode($res->getBody());

        $checkedInResult = DB::select('SELECT COUNT(*) as checkedInCount FROM user_places_checked_in WHERE place_id = ?', [$placeId]);
        $place->result->checkedInCount = $checkedInResult[0]->checkedInCount;

        //header('Content-Type: application/json');
        
        return \Response::json($place,200);
        //echo json_encode($place);
        
    }

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
