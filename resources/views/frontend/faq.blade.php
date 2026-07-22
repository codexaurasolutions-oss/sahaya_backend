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
        {!! $cmsDescription->body ?? ''!!}        
            <div class="faq-page-block">
                <div class="accordion" id="common-questions">
                    <div class="accordion DashboardBoxFrame" id="accordionfaq1">
                        @foreach($cmsFaq as $index => $faq)
                        <div class="accordion-item {{$index == 0 ? 'active' : ''}}">
                            <div class="accordion-header" id="heading21">
                                <h2 class="accordion-button faq-accord {{$index == 0 ? '' : 'collapsed'}}" data-bs-toggle="collapse"
                                    data-bs-target="#collapse21_{{$index}}" aria-expanded="{{$index == 0 ? 'true' : 'false'}}"
                                    aria-controls="collapse21_{{$index}}">
                                    {{$faq->faqDiscription->question ?? ''}}
                                </h2>
                            </div>
                            <div id="collapse21_{{$index}}" class="accordion-collapse collapse {{$index == 0 ? 'show' : ''}}"
                                aria-labelledby="heading21" data-bs-parent="#accordionfaq1">
                                <div class="accordion-body">
                                    <div class="faq_Questions_title">
                                        <p>
                                            {{$faq->faqDiscription->answer ?? ''}}
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
    </div>

</div>

@stop