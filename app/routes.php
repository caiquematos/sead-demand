<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/


//Order the routes from child to parent


if(Session::get("user") == null){

	Route::controller('/gcm', 'GCMController');
	Route::controller('/user', 'UserController');
	Route::controller('/demand', 'DemandController'); //TODO: To fix this, shouldn't be here (But android doesnt have Session)
	
	Route::get('/', function()
	{
		return View::make('hello'); //TODO: create view for login and replace (web version)
	});
	
} else {
	
	Route::controller('/gcm', 'GCMController');
	Route::controller('/history', 'HistoryController');
	Route::controller('/demand', 'DemandController');
	
	Route::get('/logout', function(){
		Session::flush();
		return Redirect::guest("/")->with("Você saiu da sua conta"); //TODO: this message should reach login view
	});
	
	Route::get('/', function()
	{
		return "Usuário logado!"; //TODO: create view for login and replace (web version)
	});

}

