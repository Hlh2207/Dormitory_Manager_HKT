<?php
// ============================================================
//  Validator.php — Kiểm tra dữ liệu đầu vào bằng Regex
//  Task 5: Bắt lỗi dữ liệu đầu vào (Validation)
//
//  Quy tắc:
//   - Email   : phải có đuôi @gmail.com hoặc @<tên trường>.edu.vn
//   - SĐT     : đúng 10 số, bắt đầu bằng số 0
//   - CCCD    : đúng 12 chữ số
//
//  Nếu dữ liệu sai, hàm trả về thông báo lỗi (string).
//  Nếu dữ liệu đúng, hàm trả về null.
//  => Dùng chung được cho mọi form (thêm SV, sửa SV, đăng ký...)
// ============================================================

class Validator
{
    // ------------------------------------------------------
    // 1. KIỂM TRA EMAIL
    //    Hợp lệ: nguyenvana@gmail.com
    //             nguyenvana@dainam.edu.vn
    //    Không hợp lệ: nguyenvana@yahoo.com, abc@gmail.con, ...
    // ------------------------------------------------------
    public static function validateEmail(string $email): ?string
    {
        $pattern = '/^[\w.+\-]+@(gmail\.com|[\w\-]+\.edu\.vn)$/i';

        if ($email === '') {
            return 'Email không được để trống.';
        }
        if (!preg_match($pattern, $email)) {
            return 'Email phải có đuôi @gmail.com hoặc @<tên trường>.edu.vn';
        }
        return null; // Hợp lệ
    }

    // ------------------------------------------------------
    // 2. KIỂM TRA SỐ ĐIỆN THOẠI
    //    Hợp lệ: 0912345678 (đúng 10 số, bắt đầu bằng 0)
    //    Không hợp lệ: 912345678, 09123456789, 0912-345-678
    // ------------------------------------------------------
    public static function validatePhone(string $phone): ?string
    {
        $pattern = '/^0[0-9]{9}$/';

        if ($phone === '') {
            return 'Số điện thoại không được để trống.';
        }
        if (!preg_match($pattern, $phone)) {
            return 'Số điện thoại phải đủ 10 số và bắt đầu bằng số 0.';
        }
        return null; // Hợp lệ
    }

    // ------------------------------------------------------
    // 3. KIỂM TRA SỐ CCCD
    //    Hợp lệ: đúng 12 chữ số, ví dụ 079203000101
    //    Không hợp lệ: thiếu/dư số, có chữ cái, có khoảng trắng
    // ------------------------------------------------------
    public static function validateIdCard(string $idCard): ?string
    {
        $pattern = '/^[0-9]{12}$/';

        if ($idCard === '') {
            return 'Số CCCD không được để trống.';
        }
        if (!preg_match($pattern, $idCard)) {
            return 'Số CCCD phải đúng 12 chữ số.';
        }
        return null; // Hợp lệ
    }

    // ------------------------------------------------------
    // 4. KIỂM TRA TOÀN BỘ FORM SINH VIÊN MỘT LẦN
    //    Trả về mảng lỗi (key = tên field, value = thông báo lỗi)
    //    Mảng rỗng => không có lỗi => được phép lưu DB
    // ------------------------------------------------------
    public static function validateStudentForm(array $data): array
    {
        $errors = [];

        // ----- Họ tên -----
        if (trim($data['full_name'] ?? '') === '') {
            $errors['full_name'] = 'Họ tên không được để trống.';
        }

        // ----- Mã sinh viên -----
        if (trim($data['student_code'] ?? '') === '') {
            $errors['student_code'] = 'Mã sinh viên không được để trống.';
        }

        // ----- Giới tính -----
        if (!in_array($data['gender'] ?? '', ['male', 'female', 'other'])) {
            $errors['gender'] = 'Vui lòng chọn giới tính.';
        }

        // ----- Email (Regex) -----
        $emailError = self::validateEmail(trim($data['email'] ?? ''));
        if ($emailError) {
            $errors['email'] = $emailError;
        }

        // ----- Số điện thoại (Regex) -----
        $phoneError = self::validatePhone(trim($data['phone'] ?? ''));
        if ($phoneError) {
            $errors['phone'] = $phoneError;
        }

        // ----- Số CCCD (Regex) -----
        $idCardError = self::validateIdCard(trim($data['id_card'] ?? ''));
        if ($idCardError) {
            $errors['id_card'] = $idCardError;
        }

        // ----- Ngày sinh -----
        if (trim($data['date_of_birth'] ?? '') === '') {
            $errors['date_of_birth'] = 'Vui lòng nhập ngày sinh.';
        }

        // ----- Khoa -----
        if (trim($data['faculty'] ?? '') === '') {
            $errors['faculty'] = 'Vui lòng nhập khoa.';
        }

        // ----- Ngành -----
        if (trim($data['major'] ?? '') === '') {
            $errors['major'] = 'Vui lòng nhập ngành.';
        }

        // ----- Năm nhập học -----
        if (!preg_match('/^[0-9]{4}$/', trim($data['intake_year'] ?? ''))) {
            $errors['intake_year'] = 'Năm nhập học phải là 4 chữ số.';
        }

        // ----- Lớp -----
        if (trim($data['class_name'] ?? '') === '') {
            $errors['class_name'] = 'Vui lòng nhập lớp.';
        }

        return $errors;
    }
}
