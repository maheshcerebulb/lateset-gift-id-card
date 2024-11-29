<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Template</title>
  </head>
  <body style="margin: 0; padding: 0">
       @php
        $statusColors = [
            'Approved' => '#008000',
            'Draft' => '#808080',
            'Submitted' => '#0000FF',
            'Rejected' => '#FFA500',
            'Expired' => '#FFB6C1',
    		'Surrendered' => '#FFFF00',
    		'Deactivated' => '#000000',
    		'Activated' => '#90EE90',
    		'Verified' => '#006400',
    		'Send Back' => '#FF8C00',
    		'Hard copy submitted' => '#800080',
    		'Terminated' => '#FF0000',
    		'Blocked' => '#8B0000',
        ];
        if(isset($emailData) && !empty($emailData)){
            $status = Helper::getApplicationType($emailData['data']['status']);
            $statusBgColor = !empty($statusColors[$status])? $statusColors[$status]: "#008000";
        } else {
            $statusBgColor = "#008000";
        }
    @endphp
    <table border="0" cellpadding="0" cellspacing="0" width="600" style="border-spacing: 0;font-family: Arial, sans-serif;border: 1px solid #c0c0c0;">
      <tr>
        <td align="center" style="">
          <table>
            <tr>
              <td style="border-spacing: 0;width: 50%; height: 120px;background: {{$statusBgColor}};">
                <h3 style="font-weight: bold;margin-left: 20px;color: #ffffff;">Your ID-Card application <br />
                has been {{ ucfirst(Helper::getApplicationType($emailData['data']['status'])) }}</h3>
              </td>
              <td style="border-spacing: 0;width: 50%;" bgcolor="#f0f0f0">
                <img src="{{ asset('img/gift-logo-gray-content.png') }}" style="" />
              </td>
            </tr>
            <tr>
              <td
                colspan="3"
                style="padding: 20px 10px 5px;font-family: Arial, sans-serif;color: #333333;font-size: 16px;">
                Dear {{ ucwords(strtolower($emailData['data']['authorized_signatory'])) }},
              </td>
            </tr>
            <tr>
              <td
                colspan="3"
                style="
                  padding: 5px 10px 10px;
                  font-family: Arial, sans-serif;
                  color: #333333;
                  font-size: 16px;
                "
              >
                <p>
                    @if(Helper::getApplicationType($emailData['data']['status']) == 'Submitted')
                        @if($emailData['data']['application_type'] == 1)
                            We would like to inform you that the entity application with Application Number {{ $emailData['data']['application_number'] }} has been successfully Renewed.
                        @elseif($emailData['data']['application_type'] == 0)
                            We would like to inform you that the entity application with Application Number {{ $emailData['data']['application_number'] }} has been successfully Submitted.
                        @else
                            We would like to inform you that the entity application with Application Number {{ $emailData['data']['application_number'] }} has been successfully Surrendered.
                        @endif
                    @else
                      We would like to inform you that the entity application with the following details has been <strong>{{ ucfirst(Helper::getApplicationType($emailData['data']['status'])) }}</strong>
                    @endif
                </p>
              </td>
            </tr>
          </table>
          <table
            border="0"
            cellpadding="0"
            cellspacing="0"
            width="600"
            style="padding: 10px"
            class="borderleftright"
          >
            <tbody>
              <tr>
                <td>
                  <table style="border: 1px solid #c0c0c0; border-radius: 10px; width: 100%; border-spacing: 0; overflow: hidden;">
                    <thead>
                      <tr style="background-color: #f1f1f1;">
                        <th colspan="2" style="padding: 12px; font-family: Arial, sans-serif; color: #333; font-size: 16px; text-align: left; border-bottom: 1px solid #c0c0c0;">Details</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td style="background-color: #f9f9f9;width: 35%; padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-right: 1px solid #c0c0c0;border-bottom: 1px solid #c0c0c0;">Serial Number</td>
                        <td style="background-color: #f9f9f9;width: 65%; padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ ($emailData['data']['final_special_serial_no'])? $emailData['data']['final_special_serial_no']:$emailData['data']['serial_no'] }}</td>
                      </tr>
                      <tr>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Name</td>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ $emailData['data']['first_name'].' '.$emailData['data']['last_name'] }}</td>
                      </tr>
                      <tr>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Designation</td>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ $emailData['data']['designation'] }}</td>
                      </tr>
                      <tr>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Mobile No.</td>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ $emailData['data']['mobile_number'] }}</td>
                      </tr>
                      <tr>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Type</td>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">
                            @if ($emailData['data']['type'] == 'Other')
                                {{$emailData['data']['type']}} ( {{($emailData['data']['sub_type'] == 'Government') ? 'Government': 'Non-government'}} )
                            @else
                                {{$emailData['data']['type']}}
                            @endif
                        </td>
                      </tr>
                      <tr>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Auth. Signatory</td>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ $emailData['data']['authorized_signatory'] }}</td>
                      </tr>
                      <tr>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; border-bottom: 1px solid #c0c0c0;border-right: 1px solid #c0c0c0;">Issue Date</td>
                        <td style="background-color: #f9f9f9;padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold; border-bottom: 1px solid #c0c0c0;">{{ date('d-m-Y',strtotime($emailData['data']['issue_date'])) }}</td>
                      </tr>
                      
                      <tr>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px;border-right: 1px solid #c0c0c0;">Expire Date</td>
                        <td style="padding: 10px; font-family: Arial, sans-serif; color: #333333; font-size: 14px; font-weight: bold;">
                            @if ($emailData['data']['type'] != 'Other')
                            {{ date('d-m-Y',strtotime($emailData['data']['expire_date'])) }}
                            @else
                            Till Posting in SEZ
                            @endif
                       </td>
                      </tr>
                    </tbody>
                  </table>
                  
                </td>
              </tr>
            </tbody>
          </table>
          <table
            class="borderleftright"
            cellpadding="0"
            cellspacing="0"
            width="598"
            style="background-color: #ffffff;"
          >
            <tr>
              <td
                colspan="2"
                style="
                  padding: 20px;
                  font-family: Arial, sans-serif;
                  color: #333333;
                  font-size: 16px;
                  line-height: 20px;
                "
              >
                <b> Thanks, </b>
                <br />
                GIFT SEZ
              </td>
            </tr>
          </table>
          <table
            cellpadding="0"
            cellspacing="0"
            width="598"
            height="4"
            style="background-color: {{$statusBgColor}};"
          >
            <tbody>
              <tr></tr>
            </tbody>
          </table>
          <table
            cellpadding="0"
            cellspacing="0"
            width="598"
            style="background-color: #f2f2f2;
            "
          >
            <tbody>
              <tr>
                <td
                  style="
                    padding: 20px 10px;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color: #666666;
                  "
                >
                  For any other inquiry:
                  <br />
                  Email: <b>giftsezcustoms@gmail.com</b>
                </td>
                <td
                  style="
                    padding: 20px 10px;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color: #666666;
                  "
                >
                  For technical support
                  <br />
                  Email: <b>ground24@cerebulb.com</b>
                </td>
                <td
                  style="
                    padding: 5px;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color: #666666;
                    text-align: center;

                    /* padding-left: ; */
                  "
                >
                  <div style="margin-bottom: 8px">
                    <a href="http://www.cerebulb.com" target="_blank">
                        <img width="121px" src="{{ asset('img/developed-by.png') }}" style="margin: 10px 0px 10px 5px; border: none;" />
                    </a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <table
            cellpadding="0"
            cellspacing="0"
            width="598"
            height="4"
          >
            <tbody>
              <tr>
                <td
                  colspan="3"
                  style="
                    font-family: Arial, sans-serif;
                    background-color: #f2f2f2;
                    font-size: 16px;
                    padding-bottom: 10px;
                    text-align: center;
                  "
                >
                  www.giftgujarat.in
                </td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>