<?php

namespace App\Http\Controllers\frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Auth;
use App\Models\ProductVariant;
use App\Models\ReviewRating;
use App\Models\User;
use App\Models\Product;
use Carbon\Carbon;
use App\Models\ProductSize;
use App\Models\ShippingAddressModel;
use App\Models\ProductColor;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Notification;
use App\Models\HowItWork;
use App\Models\HowItWorkDescription;
use App\Models\Testimonial;
use App\Models\Language;
use App\Models\Cms;
use App\Models\Faq;
use App\Models\CmsDescription;
use App\Http\Controllers\frontend\webRecaptchaController;
use App\Models\ContactUs;
use App\Models\AboutAyva;
use DB;
use Config;
use Illuminate\Support\Facades\Http;
use App;
use Validator;
use Session;

class WebHomepageController extends Controller
{
    
     public function langChange(Request $request)
     {
        
         $lang = in_array($request->lang, ['en', 'tr']) ? $request->lang : 'en';   
         \App::setLocale($lang);    
         session()->put('locale', $lang);         
         return redirect()->back();
     }



    public function index(Request $request){
        // dd(\App::getLocale()); 
        $langCode = \App::getLocale() ?? 'en';
        
        $lang  = Language::where('lang_code',$langCode)->first();
        $howitwokscustomer = HowItWork::with(['howitworkDes' => function($query) use ($lang) {  $query->where('language_id', $lang->id); }])->where('type','customer')->get();
        $howitwoksvendor = HowItWork::with(['howitworkDes' => function($query) use ($lang) {  $query->where('language_id', $lang->id);   }])->where('type', 'vendor')->get();
        $testimonials = Testimonial::with('TestimonialDescription')->where('is_active', 1)->where('is_deleted', 0)->get()->map(function ($testimonial) use ($lang) { $testimonialDescription = $testimonial->TestimonialDescription->where('language_id', $lang->id)->first();            
            $testimonial->TestimonialDescription = $testimonialDescription;
            return $testimonial;
        });
        // dd($testimonials->toArray());
        $howItWorkCms = Cms::with(['cmsDescription' => function($query) use ($lang) {  $query->where('language_id', $lang->id);  }])->where('slug','how-it-works')->first();
        $introHomeCms = Cms::with(['cmsDescription' => function($query) use ($lang) {  $query->where('language_id', $lang->id);  }])->where('slug','intro-section')->first();
        $testimonialCms = Cms::with(['cmsDescription' => function($query) use ($lang) { $query->where('language_id', $lang->id); }])->where('slug','testimonials')->first();
        $downloadapp = Cms::with(['cmsDescription' => function($query) use ($lang) { $query->where('language_id', $lang->id);   }])->where('slug', 'download-the-app')->first();        
        if ($downloadapp && $downloadapp->cmsDescription) {  $cmsDescription = $downloadapp->cmsDescription->first();
            if ($cmsDescription) {
                $description = $cmsDescription->description;
                $title = $cmsDescription->title;
            } else {
                $description = null;
                $title = null;
            }
        } else {
            $description = null;
            $title = null;
        } ;
        $aboutAyva = Cms::with(['cmsDescription' => function($query) use ($lang) {   $query->where('language_id', $lang->id);  }])->where('slug','about-ayva')->first();
        return View("frontend.index",compact('aboutAyva','introHomeCms','downloadapp','testimonialCms','testimonials','howItWorkCms','howitwoksvendor','howitwokscustomer','lang'));
    }

    public static function verify($token)
    {
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret'),
            'response' => $token,
        ]);
        return $response->json();
    }

    public function contact(Request $request){
        if ($request->isMethod('GET')) {
            $langCode = \App::getLocale() ?? 'en';
            app()->setLocale($langCode);
            $lang  = Language::where('lang_code',$langCode)->first();
            $contactCms = Cms::with(['cmsDescription' => function($query) use ($lang) {
                $query->where('language_id', $lang->id);
            }])->where('slug', 'contact-page')->first(); 
             return View("frontend.contact",compact('contactCms'));
        }else{
            $recaptchaResponse = $request->input('g-recaptcha-response');
            $secretKey = env('GOOGLE_CAPTCHA_SECRET_KEY');
            try {
                $validated = $request->validate([
                    'recaptcha_token' => 'required',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect()->back()
                    ->withErrors($e->errors())
                    ->withInput();
            }
            $recaptcha = $this->verify($request->input('recaptcha_token'));    
            if (!$recaptcha['success'] || $recaptcha['score'] < 0.5) {
                return back()->withErrors(['recaptcha_token' => 'reCAPTCHA verification failed.']);
            } 
           
            $validated = $request->validate([
                'name' => 'required',
                'email' => 'required|email',
                'mobile' => 'required|numeric',
                'message' => 'required|max:1000',
            ]
            , [
                'email.required' => trans('messages.The_email_field_is_required'),
                'email.email' => trans('messages.This_email_is_invalid'),
                'name.required' => trans('messages.The_name_field_is_required'),
                'mobile.required' => trans('messages.The_mobile_field_is_required'),
                'mobile.numeric' => trans('messages.The_mobile_field_is_must_be_numeric'),
                'message.required' => trans('messages.The_message_field_is_required'),
                'message.string' => trans('messages.The_message_must_be_a_string'),
                'message.max' => trans('messages.The_message_must_be_greater'),
            ]
        ); 
            $contact                   = new ContactUs;
            $contact->name             = $request->name;
            $contact->message             = $request->message;
            $contact->email            = $request->email;
            $contact->mobile_number    = $request->mobile;
            $contact->country_code     = $request->mobileCode;
            $contact->save();
            $langCode = \App::getLocale() ?? 'en';
            if($langCode == "en"){
                return response()->json([
                    'success' => true,
                    'message' => 'Your message has been sent successfully!'
                ]);
            }else{
                return response()->json([
                    'success' => true,
                    'message' => 'Mesajınız başarıyla gönderildi!'
                ]); 
            }
           
        }

    }

    public function faq() {
        // dd(session()->get('locale'));
        $langCode = \App::getLocale() ?? 'en';
        $lang  = Language::where('lang_code',$langCode)->first();
        $cmsFaq = Faq::with(['faqDiscription' => function($query) use($lang) {
            $query->where('language_id', $lang->id);
        }])->where('is_active', 1)->orderBy('faq_order', 'asc')->get();
        $cmsPage = Cms::where('slug', 'faqs')->first();
        $cmsDescription = CmsDescription::where('parent_id', $cmsPage->id)->where('language_id', $lang->id)->first();
        return View("frontend.faq", compact('cmsPage', 'cmsDescription', 'cmsFaq'));
    }


    public function privacyPolicy() {
        $langCode = \App::getLocale() ?? 'en';
        $lang  = Language::where('lang_code',$langCode)->first();
        $cmsPage = Cms::where('slug', 'privacy-policy')->first();
        $cmsDescription = CmsDescription::where('parent_id', $cmsPage->id)->where('language_id', $lang->id)->first();
        return View("frontend.privacy-policy", compact('cmsPage', 'cmsDescription'));
    }

    public function termAndCondition() {
        $langCode = \App::getLocale() ?? 'en';
        $lang  = Language::where('lang_code',$langCode)->first();
        $cmsPage = Cms::where('slug', 'term-conditions')->first();
        $cmsDescription = CmsDescription::where('parent_id', $cmsPage->id)->where('language_id', $lang->id)->first();
        return View("frontend.term-and-condition", compact('cmsPage', 'cmsDescription'));
    }

    public function aboutpage(Request $request){
        $langCode = \App::getLocale() ?? 'en';
        $lang  = Language::where('lang_code',$langCode)->first();
        $cmsPage = Cms::where('slug', 'about-ayva')->first();
        $cmsPageDownload = Cms::where('slug', 'download-the-app')->first();
        $cmsDescrition = CmsDescription::where('parent_id', $cmsPage->id)->where('language_id', $lang->id)->first();
        $cmsDownloadDescrition = CmsDescription::where('parent_id', $cmsPageDownload->id)->where('language_id', $lang->id)->first();
        return View("frontend.about-us", compact('cmsPage', 'cmsPageDownload', 'cmsDescrition', 'cmsDownloadDescrition'));
    }

}