<?php
// ============================================================
//  Validator.php — Input Validation via Regex
// ============================================================

class Validator
{
    public static function validateEmail(string $email): ?string
    {
        $pattern = '/^[\w.+\-]+@(gmail\.com|[\w\-]+\.edu\.vn)$/i';

        if ($email === '') return 'Email cannot be empty.';
        if (!preg_match($pattern, $email)) return 'Email must end with @gmail.com or @<university>.edu.vn';
        return null;
    }

    public static function validatePhone(string $phone): ?string
    {
        $pattern = '/^0[0-9]{9}$/';

        if ($phone === '') return 'Phone number cannot be empty.';
        if (!preg_match($pattern, $phone)) return 'Phone number must be 10 digits and start with 0.';
        return null;
    }

    public static function validateIdCard(string $idCard): ?string
    {
        $pattern = '/^[0-9]{12}$/';

        if ($idCard === '') return 'ID Card cannot be empty.';
        if (!preg_match($pattern, $idCard)) return 'ID Card must be exactly 12 digits.';
        return null; 
    }

    public static function validateStudentForm(array $data): array
    {
        $errors = [];

        if (trim($data['full_name'] ?? '') === '') $errors['full_name'] = 'Full name cannot be empty.';
        if (trim($data['student_code'] ?? '') === '') $errors['student_code'] = 'Student ID cannot be empty.';
        if (!in_array($data['gender'] ?? '', ['male', 'female', 'other'])) $errors['gender'] = 'Please select a gender.';
        
        $emailError = self::validateEmail(trim($data['email'] ?? ''));
        if ($emailError) $errors['email'] = $emailError;

        $phoneError = self::validatePhone(trim($data['phone'] ?? ''));
        if ($phoneError) $errors['phone'] = $phoneError;

        $idCardError = self::validateIdCard(trim($data['id_card'] ?? ''));
        if ($idCardError) $errors['id_card'] = $idCardError;

        if (trim($data['date_of_birth'] ?? '') === '') $errors['date_of_birth'] = 'Please enter date of birth.';
        if (trim($data['faculty'] ?? '') === '') $errors['faculty'] = 'Please enter faculty.';
        if (trim($data['major'] ?? '') === '') $errors['major'] = 'Please enter major.';
        if (!preg_match('/^[0-9]{4}$/', trim($data['intake_year'] ?? ''))) $errors['intake_year'] = 'Intake year must be 4 digits.';
        if (trim($data['class_name'] ?? '') === '') $errors['class_name'] = 'Please enter class.';

        return $errors;
    }
}