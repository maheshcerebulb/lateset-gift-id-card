 @extends('layouts.login')

 @section('pageTitle', 'Login')

 @section('content')

 <div class="main d-flex flex-column flex-row-fluid login_background login login-1 login-signin-on" >

     <!--begin::Subheader-->

     <div class="container-fluid login-content flex-row-fluid d-flex flex-column justify-content-center position-relative overflow-hidden mx-auto">

         <div class="d-flex flex-column-fluid flex-lg-column-fluid">

             <div class="row w-100 d-flex justify-content-between m-0">

                 @include('auth.login-layout-left-section')

                 <div class="col-xxl-3 col-xl-4 col-lg-5 col-md-6 col-sm-8 px-2 m-auto ">

                         <!--begin::Card-->

                         <div class="card card-custom card-stretch login_form_box">

                             <!--begin::Body-->

                             <div class="login-form card-body pt-4 login-signin" id="kt_login">

                                 <!--begin::Toolbar-->

                                 <div class="mt-7 mb-7 text-center p-5 bg-white rounded" style="">

                                    <a href="{{ url('/') }}">

                                        <div class="symbol symbol-xl-100 symbol-lg-100 symbol-md-100 symbol-sm-100 symbol-xs-100">

                                            <img src="{{ asset('img/national_emblem.png')}}" alt="image">

                                        </div>

                                    </a>

                                </div>

 

                                    {{-- <form method="POST" class="form fv-plugins-bootstrap fv-plugins-framework needs-validation"

                                     action="{{ route('login') }}" id="login" novalidate=""> --}}
                                     <form class="form fv-plugins-bootstrap fv-plugins-framework" novalidate="novalidate" id="kt_login_signin_form">
                                     @csrf

                                     <!--begin::Form group-->

                                     <div class="form-group fv-plugins-icon-container has-success">

                                         <label class="font-weight-bolder text-dark">Enter email address</label>

                                         <input class="form-control h-auto py-3 px-3 rounded-lg" type="text" name="login" autocomplete="off" placeholder="Email"  required="">

                                         <div class="fv-plugins-message-container"></div>

                                     </div>

                                     <!--end::Form group-->

                                     <!--begin::Form group-->

                                     <div class="form-group fv-plugins-icon-container has-success">

                                         <label class="font-weight-bolder text-dark">Enter password</label>

                                         <input class="form-control h-auto py-3 px-3 rounded-lg" type="password" name="password" autocomplete="off" placeholder="password"  required="">

                                         <div class="fv-plugins-message-container"></div>

                                        
                                     </div>

                                     

                                     <!--end::Form group-->

                                     <!--begin::Action-->

                                     <div class="pb-lg-0 pb-5">

                       

                                         <button type="submit" id="kt_login_signin_submit" class="btn background-gift btn-block font-weight-bold px-15 py-4 ">Sign In</button>

                                     </div>

 

                                     <div class="form-group text-right flex-wrap justify-content-between align-items-center mt-3 mb-2">

                                         <a href="{{ route('password.request') }}" class="text-dark text-hover-primary">Forgot Password ?</a>

                                     </div>

                                     

                                     <!--end::Action-->

                                 </form>

                                 <div class="form-group align-items-center mt-2 mb-2">

                                     <div class="my-3 mr-2 text-center">

                                         <span class="text-dark mr-2">Don't have an account?</span>

                                         <a href="{{ route('users.register') }}" id="kt_login_signup" class="font-weight-bold">Request to Register</a>

                                     </div>

                                 </div>

                             </div>

                             <!--end::Body-->

                         </div>

                         <!--end::Card-->

                 </div>

             </div>

         </div>

         @include('auth.footer')

     </div>

 </div>

 @stop

@section('script')

<script>

    $(document).ready(function(){

        checkValidation();

    });

    function checkValidation() {

            var forms = document.getElementsByClassName('needs-validation');

            var validation = Array.prototype.filter.call(forms, function (form) {

                form.addEventListener('submit', function (event) {
                    console.log(event);
                    
                    if (form.checkValidity() === false) {

                        event.preventDefault();

                        event.stopPropagation();

                    }

                    form.classList.add('was-validated');

                }, false);
                
            });

        }
        

</script>

@endsection



