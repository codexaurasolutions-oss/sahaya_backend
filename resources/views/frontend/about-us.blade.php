@extends('frontend.layouts.layout')
@section('content')

<section class="page-titlle-section" style="background-image: url({{asset('frontend/img/title-bg.jpg')}});">
    <div class="container">
        <h1 class="pageTitle">{{trans('messages.about_us')}}</h1>
        <div class="breadcumbs">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{route('frontend.index')}}">{{trans('messages.home')}}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{trans('messages.about_us')}}</li>
                </ol>
            </nav>

        </div>
    </div>
</section>

<div class="cms-content">
    <div class="container">
        <div class="cmsImg">
            <img src="{{$cmsPage->image ?? ''}}" alt="">
        </div>

        <div class="about-h1">{{$cmsDescrition->title ?? ''}}</div>

        {!! $cmsDescrition->body ?? ''!!}

      
    </div>

</div>

<section class="app-download-wrapper">
    <div class="container">
        <div class="app-download-block">
            <div class="app-title">{{$cmsDownloadDescrition->title ?? ''}}</div>
            <div class="app-text">{{$cmsDownloadDescrition->body ?? ''}}</div>
            <div class="download-h5">{{$cmsDownloadDescrition->short_title ?? ''}}</div>
            <div class="apps-block mt-2">
                <a href="{{ config('Social.playstore') ?? "" }}" target="_blank"><img src="{{asset('frontend/img/g-pay.png')}}" alt="Play Store"></a>
                <a href="{{ config('Social.appstore') ?? "" }}" target="_blank"><img src="{{asset('frontend/img/apple.png')}}" alt="Apple Store"></a>
            </div>

            <div class="appscreen-block">
                <img src="{{$cmsPageDownload->image ?? ''}}" alt="{{$cmsPageDownload->image}}">
            </div>
        </div>
    </div>
</section>

@stop