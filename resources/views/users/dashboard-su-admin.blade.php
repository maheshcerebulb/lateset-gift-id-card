@extends('layouts.default')

@section('pageTitle', 'Dashboard')

@section('content')

<div class="main d-flex flex-column flex-row-fluid">

    <!--begin::Subheader-->

    {{-- <div class="subheader py-2 py-lg-4 subheader-transparent" id="kt_subheader">

        <div class="container d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">

            <!--begin::Info-->

            <div class="d-flex align-items-center flex-wrap mr-1">

                <!--begin::Page Heading-->

                <div class="d-flex align-items-baseline flex-wrap justify-content-between mr-5">

                    <!--begin::Page Title-->

                    <h2 class="text-dark font-weight-bold my-1 mr-5">Dashboard</h2>



                    <!--end::Page Title-->

                </div>

                <!--end::Page Heading-->

            </div>

            <div class="d-flex align-items-center">

                <!--begin::Actions-->

                <!-- <a href="{{url('/viewPendingRequestList') }}" class="btn btn-light font-weight-bold btn-lg color-button-navy">View Entity List</a> -->

                <!--end::Actions-->

            </div>

            <!--end::Info-->

        </div>

    </div> --}}

    <!--end::Subheader-->

    <!--begin::Content-->

    @php

    $data=Helper::getSuperAdminData();

    @endphp

    <div class="content flex-column-fluid" id="kt_content">

        <div class="row m-0">

            <div class="col background-light-blue px-6 py-8 rounded-xl mr-7 mb-7">

                <div class="last-line m-0">

                    <div class="overviewCard">

                        <div class="overviewCard-icon rounded-circle px-3 py-3 background-light-blue">

                            <i class="far fa-file-alt fa-1x text-blue"></i>

                        </div>

                        <div class="overviewCard-description">

                            <h3 class="overviewCard-title text-blue">{{$data['totalUnit']}}</h3>

                            <p class="overviewCard-subtitle font-weight-bolder">Total Units</p>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col background-light-purple px-6 py-8 rounded-xl mr-7 mb-7">

                <div class="last-line m-0">

                    <div class="overviewCard">

                        <div class="overviewCard-icon rounded-circle px-3 py-3 background-light-purple">

                            <i class="far fa-file-alt fa-1x text-purple"></i>

                        </div>

                        <div class="overviewCard-description">

                            <h3 class="overviewCard-title text-purple">{{$data['totalEmp']}}</h3>

                            <p class="overviewCard-subtitle font-weight-bolder">Total Employees</p>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col background-light-green px-6 py-8 rounded-xl mr-7 mb-7">

                <div class="last-line m-0">

                    <div class="overviewCard">

                        <div class="overviewCard-icon rounded-circle px-3 py-3 background-light-green">

                            <i class="far fa-file-alt fa-1x text-green"></i>

                        </div>

                        <div class="overviewCard-description">

                            <h3 class="overviewCard-title text-green">{{$data['ActiveIds']}}</h3>

                            <p class="overviewCard-subtitle font-weight-bolder">Active ID Cards</p>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col background-light-red px-6 py-8 rounded-xl mr-7 mb-7">

                <div class="last-line m-0">

                    <div class="overviewCard">

                        <div class="overviewCard-icon rounded-circle px-3 py-3 background-light-red">

                            <i class="far fa-file-alt fa-1x text-red"></i>

                        </div>

                        <div class="overviewCard-description">

                            <h3 class="overviewCard-title text-red">{{$data['InactiveIds']}}</h3>

                            <p class="overviewCard-subtitle font-weight-bolder">Inactive ID Cards</p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div>
            <div class="row">
                <div class="col-12 mb-5">
                    <a href="javascript:;" class="btn btn-primary font-weight-boldest text-uppercase left-gap" id="super_admin_buidling_companies_filter_modal">
                        Report
                    </a>
                </div>
            </div>
            <div class="row" style="gap: 25px 0px;">

                @foreach($addressData as $address)
              
                <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-6 col-sm-12 col-xs-6">

                    <div class="card">

                        <div class="card-header p-5">

                            <div class="card-title m-0">

                                <div class="card-label">

                                    <div class="font-weight-bolder font-size-sm" data-container="body" data-toggle="tooltip" data-placement="top" title="" data-original-title="{{strtoupper($address->address)}}">{{ \Illuminate\Support\Str::limit(strtoupper($address->address), 55,'...') }}</div>

                                </div>

                            </div>

                        </div>

                        <div class="card-body m-0 p-0">

                            <div class="card-dash-container">

                                @php

                                $getAddressData=Helper::getUnitData($address->address);

                                // dump($getAddressData);

                                @endphp

                            

                                {{-- <div  class="card-dash-box">

                                    <div class="font-size-h1 text-muted font-weight-bolder">{{$getAddressData['totalUnit']}}</div>

                                    <div class="font-size-sm text-muted font-weight-bold">Total Units</div>

                                </div> --}}
                                <a href="{{ url('buildingUnitListing/'.$address->address)}}" class="card-dash-box">
                                    <div class="font-size-h1  font-weight-bolder">{{$getAddressData['totalUnit']}}</div>
                                    <div class="font-size-sm  font-weight-bold">Total Units</div>
                                </a>

                                <a href="{{ url('buildingEmployeeListing/'.$address->address)}}" class="card-dash-box">
                                    <div class="font-size-h1  font-weight-bolder">{{$getAddressData['totalEmp']}}</div>

                                    <div class="font-size-sm  font-weight-bold">Total Employees</div>
                                </a>
                                
                                <a href="{{ url('buildingActiveCardListing/'.$address->address)}}" class="card-dash-box">
                                    <div class="font-size-h1  font-weight-bolder">{{$getAddressData['ActiveIds']}}</div>

                                    <div class="font-size-sm  font-weight-bold">Active ID Cards</div>
                                </a>

                                <a href="{{ url('buildingInActiveCardListing/'.$address->address)}}" class="card-dash-box">
                                    <div class="font-size-h1  font-weight-bolder">{{$getAddressData['InactiveIds']}}</div>
    
                                    <div class="font-size-sm  font-weight-bold">Inactive ID Cards</div>
                                </a>
                                

                            </div>

                        </div>

                    </div>

                </div>

                @endforeach

            </div>

            

            

        </div>



    </div>

</div>

@include('modals.filter-buidling-entity-form')
@endsection