<?php

return [

    'admin_panel_url' => env('ADMIN_PANEL_URL', 'https://sahayya-admin.vercel.app'),

    'crm' => [
        'client_id' => env('ZOHO_CRM_CLIENT_ID'),
        'client_secret' => env('ZOHO_CRM_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_CRM_REFRESH_TOKEN'),
        'redirect_uri' => env('ZOHO_CRM_REDIRECT_URI', 'https://sahayaa-backend-production.up.railway.app/api/zoho/crm/callback'),
        'data_center' => env('ZOHO_CRM_DATA_CENTER', 'in'),
    ],

    'desk' => [
        'client_id' => env('ZOHO_DESK_CLIENT_ID'),
        'client_secret' => env('ZOHO_DESK_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_DESK_REFRESH_TOKEN'),
        'redirect_uri' => env('ZOHO_DESK_REDIRECT_URI', 'https://sahayaa-backend-production.up.railway.app/api/zoho/desk/callback'),
        'org_id' => env('ZOHO_DESK_ORG_ID'),
        'data_center' => env('ZOHO_DESK_DATA_CENTER', 'in'),
    ],

    'mail' => [
        'client_id' => env('ZOHO_MAIL_CLIENT_ID'),
        'client_secret' => env('ZOHO_MAIL_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_MAIL_REFRESH_TOKEN'),
        'redirect_uri' => env('ZOHO_MAIL_REDIRECT_URI', 'https://sahayaa-backend-production.up.railway.app/api/zoho/mail/callback'),
        'data_center' => env('ZOHO_MAIL_DATA_CENTER', 'in'),
    ],

];
