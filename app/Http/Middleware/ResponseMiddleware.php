<?php 
namespace App\Http\Middleware;
use Illuminate\Http\RedirectResponse;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ResponseMiddleware {
    public function handle(
        Request $request,
        Closure $next
    ) {

        $response = $next( $request );

        if ( $request->wantsJson() ) {

            if ( $this->isView( $response ) ) {
                $originalContent = $response->getOriginalContent();
                $response               =  [];
                $response["status"]		=	"success";
                $response["msg"]		=	"";
                $response["data"]		=	$originalContent->getData();
                return response()->json($response
                    
                );
            }else if($response instanceof RedirectResponse){
                if(session()->has('error')  && !empty(session()->get('error'))){
                    $response               =  [];
                    $response["status"]		=	"error";
                    $response["msg"]		=	session()->get('error');
                    $response["data"]		=	(object)array();
                    session()->forget('error');
                    return response()->json(
                        $response
                    );
                }elseif(session()->has('errors')  && !empty(session()->get('errors')->getMessages())){
                    $response =  $this->change_error_msg_layout(session()->get('errors')->getMessages());
                    session()->forget('errors');
                    return response()->json(
                        $response
                    );
                }elseif(session()->has('flash_notice')  && !empty(session()->get('flash_notice'))){
                    $response               =  [];
                    $response["status"]		=	"success";
                    $response["msg"]		=	session()->get('flash_notice');
                    $response["data"]		=	session()->get('data') ? json_decode(session()->get('data')) : (object)array();
                    session()->forget('flash_notice');
                    session()->forget('data');
                    return response()->json(
                        $response
                    );
                }elseif($response->isRedirect()){
                  
                    $response               =  [];
                    $response["status"]		=	"error";
                    $response["msg"]		=	"";
                    $response["data"]		=	(object)array();
                    return response()->json(
                        $response
                    );
                }

            }
        }

        return $response;
    }

    public function change_error_msg_layout($errors = array())
    {
        $response = array();
        $response["status"] = "error";
        if (!empty($errors)) {
            $error_msg = "";
            foreach ($errors as $errormsg) {
                $error_msg1 = (!empty($errormsg[0])) ? $errormsg[0] : "";
                $error_msg .= $error_msg1 . ", ";
            }
            $response["msg"] = trim($error_msg, ", ");
        } else {
            $response["msg"] = "";
        }
        $response["data"] = (object) array();
        $response["errors"] = $errors;
        return $response;
    }

    private function isView($response ) {
        return (
            $response->getOriginalContent() instanceof View
        );
    }
}