<form action="{{ $company->id == null ? route('company.store') : route('company.update', ['company' => $company->id]) }}" method="POST"
    id="company_form" novalidate="" class="needs-validation">
    <div class="card-body">
        @csrf
        <div class="row">
            <div class="col-sm-12 col-lg-12">
                <div class="form-group col-sm-6">
                    <label for="name">Name</label>
                    <input class="form-control @error('name') is-invalid @enderror" name="name" type="text"
                        value="{{ old('name', $company->name) }}" required="" placeholder="company" maxlength="{{ config('constant.COMPANY_NAME_MAXLENGTH')}}" >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="valid-feedback">Looks good!</div>
                    <div class="invalid-feedback">Please enter company.</div>
                </div>
                <div class="form-group col-sm-6">
                    <label for="application_no">Application No</label>
                    <input class="form-control @error('application_no') is-invalid @enderror" name="application_no" type="text"
                        value="{{ old('application_no', $company->application_no) }}" required="" placeholder="Application Number">
                    @error('application_no')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="valid-feedback">Looks good!</div>
                    <div class="invalid-feedback">Please enter application number.</div>
                </div>
                <div class="form-group col-sm-6">
                    <label for="application_no">Tower Name</label>
                    <button type="button" style="float: right;" class="btn btn-sm btn-primary d-flex align-items-center" id="add_tower" onclick="toggleTowerSection()">+</button>
                    <select id="tower-name-drop-down" name="tower_name" class="form-control form-control @error('tower_name') is-invalid @enderror">
                        <option value="">-- Select --</option>
                        @foreach ($companyTowerList as $companyTower)
                            <option value="{{ $companyTower->tower_name }}"
                                {{ $companyTower->tower_name === $company->tower_name ? 'selected' : '' }}>
                                {{ strtoupper($companyTower->tower_name) }}
                            </option>
                        @endforeach
                    </select>
                    <input id="tower-name-input" class="form-control @error('tower_name') is-invalid @enderror" name="tower_name" type="text" style="display:none"
                        value="{{ old('tower_name', $company->tower_name) }}" required="" placeholder="Company tower Name">
                     @error('tower_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="valid-feedback">Looks good!</div>
                    <div class="invalid-feedback">Please select comapny tower.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-primary" type="submit">{{ $formAction == 'create' ? 'Save' : 'Update' }}</button>
        <a href="{{ route('company.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
<script src="{!! asset('js/company.js') !!}" type="text/javascript" ></script>
