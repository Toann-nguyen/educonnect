<x-mail::message>
# Xin chào {{ $name }},

Cảm ơn bạn đã đăng ký tài khoản tại EduConnect. Vui lòng bấm vào nút dưới đây để xác thực địa chỉ email của bạn và hoàn tất quá trình đăng ký:

<x-mail::button :url="$url">
Xác thực tài khoản
</x-mail::button>

*Đường dẫn xác thực này sẽ hết hạn sau 24 giờ.*

Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.

Trân trọng,<br>
Đội ngũ {{ config('app.name') }}
</x-mail::message>
