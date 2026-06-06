<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    public function testQr(Request $request)
    {
        Log::info('request: ' . json_encode($request));
        $request = $request->all();
        $amount = isset($request['amount']) ? $request['amount'] : '';
        $bankId = isset($request['bankId']) ? $request['bankId'] : '';
        $accNo = isset($request['accNo']) ? $request['accNo'] : '';
        $des = isset($request['memo']) ? $request['memo'] : '';

        $planText = $this->planText($amount, $bankId, $accNo, $des);

        $qr = $this->genQr($planText);
        return $qr;
        $data = [
            'acpId' => $bankId,
            'accountName' => $bankId,
            //            'qrCode' => $qr,
            'qrDataURL' => base64_encode($this->genQr($planText)),
        ];
        $response = [
            'code' => '00',
            'desc' => "Gen QR Code successful!",
            'data' => ($data),
        ];
        return $response;
    }
    public function getToken()
    {
        $token = Cache::get('token');
        if (!$token) {
            // Lấy token từ API
            $linksLogin = config('apps.url_api.ORES') . '/api/v1/login';

            $dataLogin = [
                'username' => config('apps.general.USER_NAME'),
                'password' => config('apps.general.PASS_WORD'),
            ];
            $callApiLogin = HttpRequestHelper::callApi(($dataLogin), $linksLogin, '');
            if (isset($callApiLogin->data->token) && isset($callApiLogin->data) && isset($callApiLogin)) {
                $token = $callApiLogin->data->token;
            } else {
                Log::info('Login AWS s3 that bai!!!');
            }
            // Lưu token vào bộ nhớ cache
            Cache::put('token', $token, 31104000);
        }
        return $token;
    }

    public function genQr($string)
    {
        return QrCode::size(300)->generate($string);
    }

    public function planText($amount, $bankId, $accNo, $des)
    {
        $payloadId = '00';
        $payloadLenght = '02';
        $payloadValue = '00';
        // Phiên bản dữ liệu
        $payloadFormatIndicator = $payloadId . $payloadLenght . $payloadValue;

        $pointId = '01';
        $pointLenght = '02';
        $pointValue = '11';
        //Phương thức khởi tạo
        $pointOfInitiationMethod = $pointId . $pointLenght . $pointValue;

        // Consumer Account Information : Thông tin định danh người thụ hưởng
        $consumerAccountId = '38';
        $consumerAccountValue = $this->consumerAccountValue($bankId, $accNo);

        $consumerAccountLenght = strlen($consumerAccountValue);
        $consumerAccount = $consumerAccountId . $consumerAccountLenght . $consumerAccountValue;

        //Transaction Currency: Mã tiền tệ
        $transactionCurrencyId = '53';
        $transactionCurrencyLenght = '03';
        $transactionCurrencyValue = '704';
        $transactionCurrency = $transactionCurrencyId . $transactionCurrencyLenght . $transactionCurrencyValue;

        //Transaction Amount: Số tiền GD
        $transactionAmountId = '54';

        $transactionAmountValue = $amount;
        if (strlen($transactionAmountValue) < 10) {
            $transactionAmountLenght = '0' . strlen($transactionAmountValue);
        } else $transactionAmountLenght = strlen($transactionAmountValue);

        $transactionAmount = $transactionAmountId . $transactionAmountLenght . $transactionAmountValue;

        //Country Code: Mã quốc gia
        $countryCodeId = '58';
        $countryCodeLenght = '02';
        $countryCodeValue = 'VN';
        $countryCode = $countryCodeId . $countryCodeLenght . $countryCodeValue;

        // Additional Data Field Template: Thông tin bổ sung
        $infoId = '62';
        $infoValue = '08' . strlen($des) . $des;
        $infoLenght = strlen($infoValue);

        $info = $infoId . $infoLenght . $infoValue;

        // CRC (Cyclic Redundancy Check)

        $crcId = '63';
        $crcLenght = '04';
        $checksum = $payloadFormatIndicator . $pointOfInitiationMethod . $consumerAccount . $transactionCurrency . $transactionAmount . $countryCode . $info . $crcId . $crcLenght;
        $crcValue = $this->checksum($checksum);
        $crc = $crcId . $crcLenght . $crcValue;

        $string = $payloadFormatIndicator . $pointOfInitiationMethod . $consumerAccount . $transactionCurrency . $transactionAmount . $countryCode . $info . $crc;
        return $string;
    }

    public function checksum($checksum)
    {
        $checksum = $this->crc16($checksum);
        return $checksum;
    }
    public static function crc16($data)
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
        }
        $trueCrc = strtoupper(dechex($crc));
        if (strlen($trueCrc) < 4) {
            $trueCrc = str_pad($trueCrc, 4, "0", STR_PAD_LEFT);
        }
        return $trueCrc;
    }
    public function consumerAccountValue($bankId, $accNo)
    {
        $guid = '0010A000000727';

        $serviceCodeId = '02';
        $serviceCodeLenght = '08';
        $type = $this->rule($accNo);
        if ($type == 2) {
            $serviceCodeValue = 'QRIBFTTC';
        } else {
            $serviceCodeValue = 'QRIBFTTA';
        }
        $serviceCode = $serviceCodeId . $serviceCodeLenght . $serviceCodeValue;

        $lenghtId = '01';

        if (strlen($accNo) >= 10) {
            $lenghtValue = '0006' . $bankId . '01' . strlen($accNo) . $accNo;
        } else  $lenghtValue = '0006' . $bankId . '010' . strlen($accNo) . $accNo;

        $lenghtLt = strlen($lenghtValue);

        $lenght = $lenghtId . $lenghtLt . $lenghtValue;

        $value = $guid . $lenght . $serviceCode;

        return $value;
    }

    /*
     * hiennv
     * check rule stk va so the
     */
    public function rule($accNo)
    {
        $lenght = strlen($accNo);
        // type: 1 stk - type: 2 so the
        $type = 0;
        if ($lenght == 19 || $lenght == 21 || $lenght == 16) {
            $type = 2;
        } else if (in_array($lenght, [8, 10, 11, 12, 13, 15])) {
            $type = 1;
        }

        return $type;
    }
    public function qrCode()
    {
        return view('qr-code.testqr');
    }

    public function bank()
    {
        $listBank = Bank::get();
        if (count($listBank) > 0) {
            foreach ($listBank as $bank) {
                $data = [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'code' => $bank->code,
                    'bin' => $bank->bin,
                    'shortName' => $bank->short_name,
                    'transferSupported' => $bank->transfer_supported,
                    'lookupSupported' => $bank->lookup_supported,
                ];
                $banks[] = $data;
            }

            $response = [
                "code" => "00",
                "desc" => "Get Bank list successful!",
                "data" => $banks,
            ];
            return $response;
        } else {
            $response = [
                "code" => "18",
                "desc" => "Get Bank list fail!",
            ];
            return $response;
        }
    }

    public function check() {}
    public function insertBank()
    {
        $links = 'https://api.vietqr.io/v2/banks';
        $callApi = HttpRequestHelper::callApi('', $links, '', 'get');
        if (isset($callApi->code) && $callApi->code != '00') {
            return [
                'code' => '18',
                'desc' => 'That bai!!!',
            ];
        }
        $data = $callApi->data;
        //        dd($data);
        foreach ($data as $dt) {
            $bank = new Bank();
            $bank->name = $dt->name;
            $bank->code = $dt->code;
            $bank->bin = $dt->bin;
            $bank->short_name = $dt->shortName;
            $bank->logo = $dt->logo;
            $bank->transfer_supported = $dt->transferSupported;
            $bank->lookup_supported = $dt->lookupSupported;
            $bank->created_at = date("Y-m-d H:i:s");
            $bank->updated_at = date("Y-m-d H:i:s");
            $save = $bank->save();
            if (!$save) {
                Log::info('save Banks fail!!!');
            } else {
                Log::info('save Banks success!!!');
            }
        }
    }
}
