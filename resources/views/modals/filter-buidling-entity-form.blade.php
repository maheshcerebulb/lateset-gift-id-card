<div class="modal fade" id="buidlingcompaniesDataFilterModal" tabindex="-1" role="dialog" aria-labelledby="buildingcompaniesDataFilterModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Report</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <i aria-hidden="true" class="ki ki-close"></i>
                </button>
            </div>
            <div id="reportFormContainer">
            {!! Form::open(['route' => 'user.building-companies-applications-data-filter-export', 'class' => 'form', 'id' => 'building_companies_applications_data_filter_form', 'action-for' => 'add']) !!}
                @csrf
                <div class="card-body row">
                    <div class="col-lg-12">
                        <div class="form-group row">
                            <div class="col-lg-5">
                                <label>Report Category:</label>
                                <div class="radio-inline">
                                    <label class="radio">
                                        <input type="radio" name="filter_report" value="entity" onclick="toggleIdCardData(false)" checked>
                                        <span></span>Entity Report
                                    </label>
                                    <label class="radio">
                                        <input type="radio" name="filter_report" value="Id-cards" onclick="toggleIdCardData(true)">
                                        <span></span>Id Card Report
                                    </label>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <label>Report Option:</label>
                                <div class="radio-inline">
                                    <label class="radio">
                                        <input type="radio" name="filter_report_option" value="export" checked>
                                        <span></span>Export
                                    </label>
                                    <label class="radio">
                                        <input type="radio" name="filter_report_option" value="view-report">
                                        <span></span>View Report
                                    </label>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <label>Year List: </label>
                                <select class="form-control select2 datatable-input" id="filter_year" name="filter_year">
                                    @if(count($yearsList) > 1)
                                        <option value="0">All</option>
                                    @endif
                                    @foreach ($yearsList as $row)
                                        <option value="{{ $row->year }}">{{ $row->year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-lg-6">
                                <label>Building List:</label>
                                <select onchange="getBaseCompanyList()" class="form-control select2 datatable-input" id="filter_building" name="filter_building[]" multiple>
                                        <option value="0" selected>All</option>
                                    @foreach ($filterBuildingData as $row)
                                        <option value="{{ $row->company_building }}">{{ $row->company_building }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6">
                                <label>Company List:</label>
                                <select class="form-control select2 datatable-input" id="filter_company" name="filter_company[]" multiple>
                                        <option value="0" selected>All</option>
                                        @foreach ($filterCompanyData as $row)
                                        <option value="{{ $row->company_name }}">{{ $row->company_name }}</option>
                                        @endforeach
                                </select>
                            </div>
                        </div>
                        <div id="id-card-data-div" style="display: none;">
                            <div class="form-group row">
                                <div class="col-lg-5">
                                    <label>Type:</label>
                                    <div class="radio-inline">
                                        <label class="radio">
                                            <input type="radio" name="filter_type" value="Permanent">
                                            <span></span>Permanent
                                        </label>
                                        <label class="radio">
                                            <input type="radio" name="filter_type" value="Temporary">
                                            <span></span>Temporary
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <label>Gender:</label>
                                    <div class="radio-inline">
                                        <label class="radio">
                                            <input type="radio" name="filter_gender" value="Male">
                                            <span></span>Male
                                        </label>
                                        <label class="radio">
                                            <input type="radio" name="filter_gender" value="Female">
                                            <span></span>Female
                                        </label>
                                        <label class="radio">
                                            <input type="radio" name="filter_gender" value="Other">
                                            <span></span>Other
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <label>Age Group: </label>
                                    <select class="form-control select2 datatable-input" id="filter_age_group" name="filter_age_group">
                                            <option value="0">All</option>
                                            <option value="18-20">18 to 20</option>
                                            <option value="20-30">20 to 30</option>
                                            <option value="30-40">30 to 40</option>
                                            <option value="40-inf">40 and above</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            <button id="building_companies_applications_data_filter_submit_button" type="button" class="btn btn-primary mr-2">Generate Report</button>
                        </div>
                    </div>
                </div>
            {!! Form::close() !!}
            </div>
            <div id="exportButton" style="margin:10px 0"></div>
            <div id="reportTableContainer" class="fixed-height-div" style="padding: 20px 0 20px 5px;display: none;"></div>
        </div>
    </div>
</div>
<script>
    function toggleIdCardData(show) {
      const idCardDiv = document.getElementById('id-card-data-div');
      idCardDiv.style.display = show ? 'block' : 'none';
    }
</script>
