<?php
$WEBSITE_URL				=	env("APP_URL");
$NODE_WEB_URL 				= env('NODE_APP_URL');
return [
	'ALLOWED_TAGS_XSS'   	=> '<iframe><a><strong><b><p><br><i><font><img><h1><h2><h3><h4><h5><h6><span><div><em><table><ul><li><section><thead><tbody><tr><td><figure><article>',
	'DS'     				=> '/',
	'ROOT'     				=> base_path(),
	'APP_PATH'     			=> app_path(),

	'NODE_WEBSITE_URL'                      => $NODE_WEB_URL,
	'WEBSITE_URL'                           => $WEBSITE_URL,


	"IMAGE_PATH"							=>	$WEBSITE_URL.'img/',
	"IMAGE_ROOT_PATH"						=>	"img/",

	"LANGUAGE_IMAGE_PATH"					=>	$WEBSITE_URL.'/uploads/language_image/',
	"LANGUAGE_IMAGE_ROOT_PATH"				=>	"uploads/language_image/",

	"USER_IMAGE_PATH"						=>	$WEBSITE_URL.'/uploads/User-image/',
	"USER_IMAGE_ROOT_PATH"					=>	"uploads/User-image/",

	"SEO_PAGE_IMAGE_IMAGE_PATH"		 		=>	$WEBSITE_URL.'/uploads/sep-image/',
	"SEO_PAGE_IMAGE_ROOT_PATH"				=>	"uploads/sep-image/",

	"CK_EDITOR_URL"		 					=>	$WEBSITE_URL . 'uploads/ck_editor_images/',
	"CK_EDITOR_ROOT_PATH"					=>	"uploads/ck_editor_images/",
	
	"MOBILE_INTRO_IMAGE"					=>	$WEBSITE_URL.'/uploads/intro-image/',
	"MOBILE_INTRO_IMAGE_ROOT_PATH"			=>	"/uploads/intro-image/",

    "INTRO_SECTION_IMAGE"					=>	$WEBSITE_URL.'/uploads/intro-section-image/',
	"INTRO_SECTION_IMAGE_ROOT_PATH"			=>	"/uploads/intro-section-image/",

    "CMS_PAGE_IMAGE"					=>	$WEBSITE_URL.'/uploads/cms-page-image/',
	"CMS_PAGE_IMAGE_ROOT_PATH"			=>	"/uploads/cms-page-image/",

    "ABOUT_AYVA_IMAGE"					=>	$WEBSITE_URL.'/uploads/about-ayva-image/',
	"ABOUT_AYVA_IMAGE_ROOT_PATH"			=>	"/uploads/about-ayva-image/",

	"CATEGORY_IMAGE"						=>	$WEBSITE_URL.'/uploads/categories/',
	"CATEGORY_IMAGE_ROOT_PATH"				=>	"/uploads/categories/",

	"PRODUCT_VIDEO_PATH"					=>	$WEBSITE_URL.'/uploads/product_video/',
	"PRODUCT_VIDEO_ROOT_PATH"				=>	"/uploads/product_video/",

	"REFUND_IMAGE_PATH"					=>	$WEBSITE_URL.'/uploads/refund_image/',
	"REFUND_IMAGE_ROOT_PATH"				=>	"/uploads/refund_image/",

	"PRODUCT_VIDEO_THUMBNAIL_PATH"			=>	$WEBSITE_URL.'/uploads/product_video/thumbnail/',
	"PRODUCT_VIDEO_THUMBNAIL_ROOT_PATH"		=>	"/uploads/product_video/thumbnail/",

	'MESSAGE' => [
		'INACTIVE_MEMBER_STAFF' => "You can't login in site panel, please contact to site admin!",
	],

	'GENDER' => [
		'1' 	=> "Men",
		'2' 	=> "Women",
		'0' 	=> "Other",
	],

	'CUSTOMER' => [
		'CUSTOMERS_TITLE' 	=> "Customer",
		'CUSTOMERS_TITLES' 	=> "Customers",
	],

	'REPORTS' => [
		'ORDER_REVENUE_TITLE' 	    => "Orders & Revenue",
		'MORE_ORDER_PLACED'         =>'Customers Who Placed More Orders',
		'ORDER_COMMISSION_TITLE'    =>'Orders Commission'
	],

	'ORDER' => [
		'ORDER_TITLE' 	    => "Order",
		'ORDER_TITLES' 	    => "Orders",
		'ORDER_RETURN'      => "Product Return Details"
	],

	'PLAN' => [
		'PLAN_TITLE' 	    => "Subscription Plan",
		'PLAN_TITLES' 	    => "Subscription Plans",
	],

	'PLAN_DURATION' => [
		'monthly' 	    => "Monthly",
		'yearly' 	    => "Yearly",
	],


	
	'COUPON' => [
		'COUPON_TITLE' 	        => "Coupon",
		'COUPON_TITLES' 	    => "Coupons",
	],

	'ENQUIRIE' => [
		'ENQUIRIE_TITLE' 	        => "Enquiry",
		'ENQUIRIE_TITLES' 	    => "Enquiries",
	],
	'TRANSACTION' => [
		'TRANSACTION_TITLE' 	        => "Transaction",
		'TRANSACTION_TITLES' 	    => "Transactions",
	],

	'MOBILE_INTRO_SCREEN' => [
		'MOBILE_INTRO_SCREEN_TITLE' 	=> "Intro Screen",
		'MOBILE_INTRO_SCREEN_TITLES' 	=> "Intro Screens",
	],

    'INTRO_SECTION' => [
		'INTRO_SECTION_TITLE' 	=> "Intro Section",
		'INTRO_SECTION_TITLES' 	=> "Intro Sections",
	],

    'ABOUT_AYVA' => [
		'ABOUT_AYVA_TITLE' 	=> "About Ayva",
		'ABOUT_AYVA_TITLES' 	=> "About Ayvas",
	],

	'SEO' => [
		'SEO_TITLE' 	=> "Seo pages",
	],

	'CMS_MANAGER' => [
		'CMS_PAGES_TITLE' 	=> "Cms Pages",
		'CMS_PAGE_TITLE' 	=> "Cms Page",
		'VIEW_PAGE' 		=> "View Page",
	],

	'FAQ' => [
		'FAQ_TITLE'	 => "Faq",
		'FAQS_TITLE' => "Faq's",
		'VIEW_PAGE'  => "Faq View",
	],

	'EMAIL_TEMPLATES' => [
		'EMAIL_TEMPLATES_TITLE' => "Email Templates",
		'EMAIL_TEMPLATE_TITLE' 	=> "Email Template",
	],

	'EMAIL_LOGS' => [
		'EMAIL_LOGS_TITLE' 		=> "Email Logs",
		'EMAIL_DETAIL_TITLE' 	=> "Email Detail",
	],

	'LANGUAGE_SETTING' => [
		'LANGUAGE_SETTINGS_TITLE' 	=> "Language Setting",
		'LANGUAGE_SETTING_TITLE' 	=> "Language Setting",
	],

	'ACL' => [
		'ACLS_TITLE' => "Acl",
		'ACL_TITLE' => "Acl Management",
	],

	'SETTING' => [
		'SETTINGS_TITLE' 	=> "Settings",
		'SETTING_TITLE' 	=> "Setting",
	],

	'DESIGNATION' => [
		'DESIGNATIONS_TITLE' 	=> "Roles",
		'DESIGNATION_TITLE' 	=> "Role",
	],

	'STAFF' => [
		'STAFFS_TITLE' 		=> "Staff's",
		'STAFF_TITLE' 		=> "Staff",
	],

	'CONTACT_QUERY' => [
		'CONTACT_QUERYS_TITLE' 	=> "Contact Query's",
		'CONTACT_QUERY_TITLE' 	=> "Contact Query",
	],

	'COLOR' => [
		'COLOR_TITLES' 	=> "Colors",
		'COLOR_TITLE' 	=> "Color",
	],


	'COUPON' => [
		'COUPONS_TITLES' 	=> "Coupons",
		'COUPON_TITLE' 	=> "coupon",
	],


	'SIZE' => [
		'SIZE_TITLES' 	=> "Sizes",
		'SIZE_TITLE' 	=> "Size",
	],

	'PRODUCT' => [
		'PRODUCT_TITLES' 	=> "Products",
		'PRODUCT_TITLE' 	=> "Product",
	],

	'CATEGORY' => [
		'CATEGORY_TITLES' 	=> "Category",
		'CATEGORY_TITLE' 	=> "Category",
	],

    'TESTIMONIAL' => [
		'TESTIMONIAL_TITLES' 	=> "Testimonials",
		'TESTIMONIAL_TITLE' 	=> "Testimonial",
	],

	'SUBCATEGORY' => [
		'SUBCATEGORY_TITLES' 	=> "Sub Category",
		'SUBCATEGORY_TITLE' 	=> "Sub Category",
	],

	'ROLE_ID' => [
		'ADMIN_ID' 					=> 1,
		'SUPER_ADMIN_ROLE_ID' 		=> 1,
		'CUSTOMER_ROLE_ID' 			=> 2,
		'STAFF_ROLE_ID' 			=> 3,
	],

	'DEFAULT_LANGUAGE' => [
		'FOLDER_CODE' 	=> 'en',
		'LANGUAGE_CODE' => 1,
		'LANGUAGE_NAME' => 'English'
	],

	'SETTING_FILE_PATH'	=> base_path() . "/" .'config'."/". 'settings.php',

	'WEBSITE_ADMIN_URL' => base_path() . "/" .'adminpnlx',

];
