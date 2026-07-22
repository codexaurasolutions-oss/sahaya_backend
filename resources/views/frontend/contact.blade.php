@extends('frontend.layouts.layout')
@section('content')


<section class="page-titlle-section" style="background-image: url({{ asset('frontend/img/title-bg.jpg') }});">
    <div class="container">
        <h1 class="pageTitle">{{trans('messages.contact_us')}}</h1>
        <div class="breadcumbs">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('frontend.index') }}">{{trans('messages.home')}}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{trans('messages.contact_us')}}</li>
                </ol>
            </nav>

        </div>
    </div>
</section>

<div class="cms-content">
    <div class="container">

        <div class="contact-block">
            {!! $contactCms->cmsDescription->body !!}
            <form method="POST" id="formContact" action="{{ route('frontend.contactSubmit') }}">
                @csrf
                <input type="hidden" name="recaptcha_token" id="recaptcha_token">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{trans('messages.Name')}}</label>
                            <input class="form-control" name="name" type="text">
                            <div id="error_name" class="text-danger"></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{trans('messages.email')}}</label>
                            <input class="form-control" name="email" type="email">
                            <div id="error_email" class="text-danger"></div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group contryList">
                            <label>{{trans('messages.phone')}}</label>
                            
                            <input type="tel" name='mobile' id='mobile' value=""
                                            class="form-control">
                                            <input type="hidden" name="full_mobile" id="full_mobile">
                                            <div id="error_mobile" class="text-danger"></div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>{{trans('messages.leave_us_a_message')}}</label>
                            <textarea class="form-control" name="message" style="height: 140px; resize: none;"></textarea>
                            <div id="error_message" class="text-danger"></div>

                        </div>
                    </div>

                    <div class="col-md-12">
                        <input type="hidden" name="g-recaptcha-response" value="{{ env('GOOGLE_CAPTCHA_SITE_KEY') ?? '' }}">
                        <button type="button" id="contactSubmit" class="btn primary-btn submit-btn">{{trans('messages.send_message')}}</button>
                    </div>

                </div>
            </form>
        </div>



    </div>

</div>
@stop


@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site') }}"></script>

<script>
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": true,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
</script>


<script>
   $(document).ready(function() {
    $('#contactSubmit').on('click', function() {
        $('.loader-wrapper').show();
        toastr.clear();
        grecaptcha.execute('{{ config('services.recaptcha.site') }}', { action: 'submit' }).then(function(token) {
            var mobileCode = $('.selected-flag .selected-dial-code').text().trim();
            let formData = {
                name: $('input[name="name"]').val(),
                email: $('input[name="email"]').val(),
                mobile: $('input[name="mobile"]').val(),
                mobileCode: mobileCode,
                message: $('textarea[name="message"]').val(),
                _token: $('input[name="_token"]').val(),
                g_recaptcha_response: token,
                recaptcha_token: token,
            };
            $.ajax({
                url: "{{ route('frontend.contactSubmit') }}",
                type: "POST",
                data: formData,
                success: function(response) {
                    $('.loader-wrapper').hide();
                    if (response.success) {
                        toastr.success(response.message);
                        $('#formContact')[0].reset();
                        $('#formContact').find('input, textarea').removeClass('is-invalid');
                        $('#formContact').find('.invalid-feedback').text('');
                        $('#formContact').find('[id^="error_"]').val('');
                        location.reload();

                    }
                },
                error: function(xhr) {
                    $('.loader-wrapper').hide();
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(index, html) {
                                $('#formContact').find('input[name="' + index + '"]')
                                    .addClass('is-invalid');
                                $('#formContact').find('textarea[name="' + index +
                                        '"]')
                                    .addClass('is-invalid');
                                $('#error_' + index).text(html).show();
                            });
                    } else {
                        toastr.error('An error occurred. Please try again.');
                    }
                }
            });
        });
    });
});

    $('body').on('input change', '.form-control', function() {
        console.log(this);
        $(this).removeClass('is-invalid');
        $(this).closest('.form-group').find('.text-danger').text('').hide();
    });

</script>
@stop