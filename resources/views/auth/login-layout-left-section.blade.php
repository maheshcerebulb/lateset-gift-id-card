<div class="d-lg-flex col-xl-7 col-lg-7 col-md-12 col-sm-12 py-0 d-md-none d-sm-none d-xs-none">

    <div class="w-100 align-self-center">

        <div class="w-100 mb-8">

            <span class="login_welcome text-dark">Welcome to<br/></span>

            <span class="login_welcome_extended">GIFT SEZ ID-Card Management System</span>

        </div>

        <div class="login_gift_site text-dark">www.giftgujarat.in</div>
        
        <div class="scan-counter-section">
            <div class="count-icon"><img src="{{ asset('img/today-scan.png')}}" alt="image"></div>
            <div class="count-content">
                <span class="count">{{ sprintf('%02d', $todayCount ?? '') }}</span><br> <span class="count-text">Today's Scans</span>
            </div>
        </div>
        <div class="scan-counter-section">
            <div class="count-icon"><img src="{{ asset('img/weekly-scan.png')}}" alt="image"></div>
            <div class="count-content">
                <span class="count">{{ sprintf('%02d', $weekCount ?? '') }}</span><br> <span class="count-text">This Week's Scans</span>
            </div>
        </div>
    </div>

</div>