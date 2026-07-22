@extends('frontend.layouts.layout')
@section('content')

<section class="page-titlle-section" style="background-image: url({{asset('frontend/img/title-bg.jpg')}});">
    <div class="container">
        <h1 class="pageTitle">{{$cmsDescription->title ?? ''}}</h1>
        <div class="breadcumbs">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{route('frontend.index')}}">{{trans('messages.home')}}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{$cmsDescription->title ?? ''}}</li>
                </ol>
            </nav>

        </div>
    </div>
</section>

<div class="cms-content">
    <div class="container">

        {!!$cmsDescription->body ?? ''!!}

    </div>

</div>

@stop