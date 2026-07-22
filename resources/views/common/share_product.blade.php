<!-- Link Swiper's CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<style type="text/css">
    .mobileWidth {
        background: #f9f9f9;
        max-width: 420px;
        margin: auto;
        font-family: sans-serif;
        box-shadow: 0 0 11px 4px rgba(0 0 0/0.2);
        border-radius: 0 0 5px 5px;
    }

    .shareTitle,
    .postUserName {
        font-size: 17px;
        font-weight: 600;
        color: #000;
        margin: 0px 0 4px 0;
    }

    .shareDetaiCard {

        padding: 15px;
    }

    .shareDetail {
        font-size: 16px;
        font-weight: 400;
        color: #524b4b;
    }

    .shareImage {
        margin: 0;
        padding: 0;
    }

    .shareImage img ,.shareImage video{
        width: 100%;
        height: 250px;
        object-fit: cover;
        object-position: center;
    }

    .appBtn {
        width: 97px;
        background: black;
        border-radius: 5px;
        position: relative;
        color: #fff;
        cursor: pointer;
        border: 1px solid #fff;
        display: block;
        vertical-align: middle;
        padding: 10px;
        padding-left: 46px;
        text-decoration: none;
    }

    .appBtn>svg {
        color: #fff;
        position: absolute;
        top: 50%;
        left: 10px;
        transform: translateY(-50%);
        width: 25px;
        height: 25px;
    }

    .appDownloadBtns {
        display: flex;
        column-gap: 10px;
        justify-content: center;
        margin-top: 20px;
    }

    .df,
    .dfn {}

    .df {
        font-size: 11px;
        display: block;
    }

    .dfn {
        font-size: 15px;
    }

    .appBtn:hover {
        -webkit-filter: invert(100%);
        filter: invert(100%);
    }

    .datePosted {
        display: block;
        margin-bottom: 10px;
        font-size: 11px;
        color: #b5b3b3;
        margin-top: 5px;
    }

    .postUserImage {
        width: 35px;
        min-width: 35px;
        height: 35px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 10px;
        border: 1px solid #000;
    }

    .postUserImage img {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
    }

    .userdetail {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .myPostSwiper {
        height: 250px;
    }

    .d-block {
        display: block;
        margin: 3px 0 0px 0;
    }

    .comment_box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 16px 0 26px;
    }

    .like_block a {
        text-decoration: none;
        margin-right: 5px;
        height: 30px;
        width: 30px;
        background-color: green;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: #fff;
    }

    .like_block a svg {
        height: 18px;
        fill: #fff;
    }

    .like_block {
        font-size: 15px;
        display: flex;
        align-items: center;
    }

    .comment_block {
        font-size: 15px;
    }

    /* comment box css */
    .userImg {
        width: 35px;
        height: 35px;
        margin: 0;
        flex: 0 0 35px;
    }

    .commentDetail {
        width: 100%;
    }

    .userImg img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
    }

    .commentBox {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 16px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 15px;
    }

    .commentTrack {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .UserName {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .commentDescription {
        margin: 5px 0 0 0;
        font-size: 13px;
    }

    .commentDate {
        font-size: 12px;
        color: #8f8c8c;
    }

    .commentTitle {
        font-size: 16px;
    }

    /* share-product css */
    .share-product-box .userdetail {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .share-product-box input[type="color" i]::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .select-color-box {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }

    .select-color {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 15px;
    }

    .size-block {
        padding: 5px 10px;
        background: #e7e7e7;
        border-radius: 5px;
        margin-right: 5px;
        font-size: 15px;
    }

    .share-product-box .appBtn {
        max-width: 158px;
        width: 100%;
        gap: 8px;
        font-size: 14px;
    }

    .share-product-box .appBtn>svg {
        position: unset;
        transform: unset;
    }

    .mobileWidth.share-product-box {
        position: absolute;
        transform: translate(-50%, -50%);
        left: 50%;
        top: 50%;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
        background-color: unset;
    }

    .share-product-box .postUserName {
        font-size: 20px;
    }

    .share-product-box .shareDetailCard {
        border: 0 !important;
        /* border-top: 1px solid #ddd !important; */
        border-radius: 20px 20px 0 0 !important;
        padding: 20px !important;
        background-color: #fff !important; 
        max-width: 400px;
        margin: auto;
        overflow: hidden;
    }
    /* .share-product-box .appBtn.android:hover{
        background-color: #fff !important;
    } */
</style>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{--
    <meta property="og:title" content="Post"> --}}
    @if (!empty($product_details->name))
        <meta property="og:description" content="{{ strip_tags($product_details->name) }}">
    @endif
    @if (!empty($product_details->url_image))
        <meta property="og:image" content="{{ $product_details->url_image }}">
    @endif
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:type" content="website">
    <!-- Add other Open Graph meta tags as needed -->
    {{-- <title>Post</title> --}}
    <!-- Other head elements like stylesheets, scripts, etc. -->
</head>
<section class="mobileWidth share-product-box">
    @if (!empty($product_details))
        <div class="webAppView">
            <!-- Swiper -->
            @if ($product_details->url_image)
                <div class="swiper myPostSwiper">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <figure class="shareImage">
                                <img src="{{ $product_details->url_image ?? '' }}" alt="Image">
                            </figure>
                        </div>
            @endif
                    <!-- <div class="swiper-slide">
                                      <figure class="shareImage">
                                        <img src="https://susail.dev.obdemo.com/image.php?height=500px&image=https://susail.dev.obdemo.com/public/uploads/destinations/JUN2023/1687937356-yellow_maple_leaf-1366x768-destinations.jpg" alt="share Image">
                                      </figure>
                                    </div>
                                    <div class="swiper-slide">
                                      <figure class="shareImage">
                                        <img src="https://susail.dev.obdemo.com/image.php?height=500px&image=https://susail.dev.obdemo.com/public/uploads/destinations/JUN2023/1687937356-yellow_maple_leaf-1366x768-destinations.jpg" alt="share Image">
                                      </figure>
                                    </div> -->
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <div class="shareDetailCard"
                style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background-color: #f9f9f9; max-width: 400px; margin: auto;">
                <div class="userdetail" style="margin-bottom: 20px;">
                    <h4 class="postUserName" style="margin: 0; color: #333;">
                        {{ $product_details->name ?? '' }}<br>
                        <p style="font-size: 1em; color: #555; margin: 0;">
                            {{ $product_details->parentCategoryDetails->name ?? '' }}
                            ({{ $product_details->subCategoryDetails->name ?? '' }})
                        </p>
                    </h4>
                    <span class="d-block datePosted"
                        style="font-size: 0.9em; color: #777;">{{ $product_details->description ?? '' }}</span>
                </div>

                <div class="select-color-box">
                    {!! $product_details->prodcutColorDetails->map(function ($productColor, $index) {
            $colorCode = $productColor->colorDetails->color_code ?? '#ffffff';
            $colorName = e($productColor->colorDetails->name);
            return '
                                                    <div class="select-color">
                                                        <input type="color" id="color' . $index . '" value="' . $colorCode . '" 
                                                            style="border-radius: 50%; width: 24px; height: 24px; padding: 0; margin-right: 5px;" disabled>
                                                        ' . $colorName . '
                                                    </div>
                                                ';
        })->join(' ') !!}
                </div>

                <div style="margin-bottom: 20px;">
                    {!! $product_details->prodcutSizeDetails->map(function ($productsize) {
            $sizename = $productsize->sizeDetails->name;
            $sizeid = $productsize->sizeDetails->id;
            return "<span id='$sizeid' class='size-block' >$sizename</span>";
        })->join(' ') ?? '' !!}
                </div>

                <div class="appDownloadBtns" style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <a href="{{ Config::get('Site.google_play_store') }}" class="appBtn android"
                        style="text-decoration: none; display: flex; align-items: center; background-color: #ebebeb; color: #000; padding: 10px; border-radius: 5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" height="16" width="12" id="google-play"
                            style="margin-right: 5px;">
                            <path fill="#ffffff"
                                d="M8.32 7.68.58 15.42c-.37-.35-.57-.83-.57-1.35V1.93C.01 1.4.22.92.6.56l7.72 7.12z"></path>
                            <path fill="#FFC107"
                                d="M15.01 8c0 .7-.38 1.32-1.01 1.67l-2.2 1.22-2.73-2.52-.75-.69 2.89-2.89L14 6.33c.63.35 1.01.97 1.01 1.67z">
                            </path>
                            <path fill="#4CAF50"
                                d="M8.32 7.68.6.56C.7.46.83.37.96.29 1.59-.09 2.35-.1 3 .26l8.21 4.53-2.89 2.89z"></path>
                            <path fill="#F44336"
                                d="M11.8 10.89 3 15.74c-.31.18-.66.26-1 .26-.36 0-.72-.09-1.04-.29a1.82 1.82 0 0 1-.38-.29l7.74-7.74.75.69 2.73 2.52z">
                            </path>
                        </svg>
                        <span>Download from </br> Google Play</span>
                    </a>

                    <a href="{{ Config::get('Site.apple_play_store') }}" class="appBtn android"
                        style="text-decoration: none; display: flex; align-items: center; background-color: #000; color: white; padding: 10px; border-radius: 5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" height="16" width="12" viewBox="0 0 384 512"
                            fill="currentcolor" style="margin-right: 5px;">
                            <path
                                d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z" />
                        </svg>
                        <span>Download from </br> App Store</span>
                    </a>
                </div>
            </div>

        </div>
    @endif
</section>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<!-- Initialize Swiper -->
<script>
    var swiper = new Swiper(".myPostSwiper", {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: true,
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
    });
</script>