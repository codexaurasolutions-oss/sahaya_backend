<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', base_path());
define('APP_PATH', app_path());
define('ALLOWED_TAGS_XSS', '<iframe><a><strong><b><p><br><i><font><img><h1><h2><h3><h4><h5><h6><span><div><em><table><ul><li><section><thead><tbody><tr><td><figure><article>');
return [
	'WEBSITE_URL' =>url('/').'/',
	'LANGUAGE_IMAGE_PATH' => 'public/uploads/language_image/',
	'CATEGORY_IMAGE'     => 'public/uploads/Category-image/',
	'SUB_CATEGORY_IMAGE'     => 'public/uploads/sub-Category-image/',
	'TESTIMONIAL_IMAGE'     => 'public/uploads/testimonial/',
	'HOWITWORK_IMAGE'     => 'public/uploads/how-it-work/',
	'BLOGS_IMAGE'     => 'public/uploads/blogs/',
	'USER_IMAGE'     => 'public/uploads/User-image/',
	'HOME_SETTING'     => 'public/uploads/home-setting/',
	'ABOUT_US_IMAGE'     => 'public/uploads/about-us-images/',
	'ABOUT_US_SLIDER'     => 'public/uploads/about-us-slider/',
	'ABOUT_SETTING'     => 'public/uploads/about-setting/',

	'URL' => [],

	'MESSAGE' => [
		'INACTIVE_MEMBER_STAFF' => "You can't login in site panel, please contact to site admin!",
	],

	'USERS' => [
		'USERS_TITLE' => "Users",
		'USER_TITLE' => "User",
		'VIEW_ORDER' => "View Order",
	],

	'CMS_MANAGER' => [
		'CMS_PAGES_TITLE' => "Cms Pages",
		'CMS_PAGE_TITLE' => "Cms Page",
		'VIEW_PAGE' => "View Page",
	],

	'FAQ' => [
		'FAQ_TITLE' => "Faq",
		'FAQS_TITLE' => "Faq's",
		'VIEW_PAGE' => "Faq View",
	],

	'EMAIL_TEMPLATES' => [
		'EMAIL_TEMPLATES_TITLE' => "Email Templates",
		'EMAIL_TEMPLATE_TITLE' => "Email Template",
	],

	'EMAIL_LOGS' => [
		'EMAIL_LOGS_TITLE' => "Email Logs",
		'EMAIL_DETAIL_TITLE' => "Email Detail",
	],

	'LANGUAGE_SETTING' => [
		'LANGUAGE_SETTINGS_TITLE' => "Language Setting",
		'LANGUAGE_SETTING_TITLE' => "Language Setting",
	],

	'ACL' => [
		'ACLS_TITLE' => "Acl",
		'ACL_TITLE' => "Acl Management",
	],

	'SETTING' => [
		'SETTINGS_TITLE' => "Settings",
		'SETTING_TITLE' => "Setting",
	],

	'DESIGNATION' => [
		'DESIGNATIONS_TITLE' => "Designations",
		'DESIGNATION_TITLE' => "Designation",
	],

	'DEPARTMENT' => [
		'DEPARTMENTS_TITLE' => "Departments",
		'DEPARTMENT_TITLE' => "Department",
	],

	'CATEGORY' => [
		'CATEGORY_TITLE' => "Category",	
	],

	'SUB_CATEGORY' => [
		'SUB_CATEGORY_TITLE' => "Sub-Category",	
	],

	'STAFF' => [
		'STAFFS_TITLE' => "Staff's",
		'STAFF_TITLE' => "Staff",
	],

	'HOWITWORK' => [
		'HOWITWORK_TITLE' => "How-It-Works",
		
	],

	'TESTIMONIAL' => [
		'TESTIMONIALS_TITLE' => "Testimonials",
		'TESTIMONIAL_TITLE' => "Testimonial",
	],

	'ABOUT_IMAGE' => [
		'ABOUT_IMAGES_TITLE' => "About Us Image",
		'ABOUT_IMAGE_TITLE' => "Image",
	],

	'ABOUT_SLIDER' => [
		'ABOUT_SLIDERS_TITLE' => "About Us Slider",
		'ABOUT_SLIDER_TITLE' => "Slider",
	],

	'BLOGS' => [
		'BLOGS_TITLE' => "Blogs",
		'BLOG_TITLE' => "Blog",
	],
  
	'HOME_PAGE_UPDATE' => [
		'HOME_PAGE_UPDATE_TITLE' => "Home Page Setting",
		
	],
	'ABOUT_PAGE_UPDATE' => [
		'ABOU_PAGE_UPDATE_TITLE' => "About Page Update",	
	],

	'CONTACT_QUERIES' => [
		'CONTACT_QUERIES_TITLE' => "Contact Queries",
		'CONTACT_QUERy_TITLE' => "Contact Query",
	],


	'ROLE_ID' => [
		'ADMIN_ID' => 1,
		'SUPER_ADMIN_ROLE_ID' => 1,
		'CUSTOMER_ROLE_ID' => 2,
		'STAFF_ROLE_ID' => 3,
		'SERVICE_PROVIDER_ROLE_ID' => 4,
	],

	'DEFAULT_LANGUAGE' => [
		'FOLDER_CODE' => 'eng',
		'LANGUAGE_CODE' => 1,
		'LANGUAGE_NAME' => 'English'
	],

	//'SETTING_FILE_PATH'	=> ROOT . DS .'config'.DS. 'settings.php',
	'SETTING_FILE_PATH'	=> APP_PATH . DS . 'settings.php',

];
