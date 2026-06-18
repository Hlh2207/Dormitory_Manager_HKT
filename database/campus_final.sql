-- ============================================================
--  HỆ THỐNG QUẢN LÝ KÝ TÚC XÁ CAMPUS  (v2 - Revised)
--  Campus Dormitory Management System
--  Cập nhật theo nhận xét của giảng viên
-- ============================================================
--  THAY ĐỔI SO VỚI v1:
--    [a] Tách bảng room_types riêng (không dùng ENUM cho loại phòng)
--    [b] Thêm CHECK constraint đảm bảo room_id khớp contracts ↔ registrations
--    [c] invoices snapshot giá điện/nước tại thời điểm xuất hóa đơn
--    [d] UNIQUE cho phone, id_card; bổ sung created_by/updated_by toàn bộ
--    [e] Thay ENUM status → bảng lookup statuses (mở rộng không cần ALTER)
--    [f] image_url → lưu path/URL Cloud Storage (có thêm cột storage_provider)
-- ============================================================
ALTER DATABASE campus_dormitory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- ============================================================
-- LOOKUP TABLES  (thay thế ENUM mở rộng được — góp ý [d][e])
-- ============================================================

-- Bảng tra cứu: trạng thái dùng chung cho nhiều bảng
CREATE TABLE statuses (
    status_id    SMALLINT     NOT NULL AUTO_INCREMENT,
    category     VARCHAR(50)  NOT NULL COMMENT 'Nhóm: user, student, room, contract, invoice, violation...',
    code         VARCHAR(50)  NOT NULL,
    label_vi     VARCHAR(100) NOT NULL COMMENT 'Nhãn tiếng Việt',
    description  VARCHAR(255) DEFAULT NULL,
    sort_order   TINYINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (status_id),
    UNIQUE KEY uq_category_code (category, code)
) ENGINE=InnoDB COMMENT='Bảng tra cứu trạng thái — thay ENUM, dễ mở rộng';

INSERT INTO statuses (category, code, label_vi, sort_order) VALUES
-- user
('user',       'active',      'Đang hoạt động',         1),
('user',       'inactive',    'Đã khóa',                2),
-- student
('student',    'active',      'Đang học',               1),
('student',    'graduated',   'Đã tốt nghiệp',          2),
('student',    'suspended',   'Bảo lưu',                3),
('student',    'expelled',    'Bị đuổi học',            4),
-- room
('room',       'available',   'Còn chỗ',                1),
('room',       'full',        'Hết chỗ',                2),
('room',       'maintenance', 'Đang bảo trì',           3),
('room',       'closed',      'Đóng cửa',               4),
-- registration
('registration','pending',    'Chờ duyệt',              1),
('registration','approved',   'Đã duyệt',               2),
('registration','rejected',   'Từ chối',                3),
('registration','cancelled',  'Đã hủy',                 4),
('registration','waitlist',   'Danh sách chờ',          5),
-- contract
('contract',   'draft',       'Bản nháp',               1),
('contract',   'active',      'Đang hiệu lực',          2),
('contract',   'expired',     'Hết hạn',                3),
('contract',   'terminated',  'Chấm dứt sớm',           4),
('contract',   'suspended',   'Tạm đình chỉ',           5),
-- invoice
('invoice',    'unpaid',      'Chưa thanh toán',        1),
('invoice',    'partial',     'Thanh toán một phần',    2),
('invoice',    'paid',        'Đã thanh toán',          3),
('invoice',    'overdue',     'Quá hạn',                4),
('invoice',    'cancelled',   'Đã hủy',                 5),
-- violation
('violation',  'open',        'Chưa xử lý',             1),
('violation',  'resolved',    'Đã xử lý',               2),
('violation',  'appealing',   'Đang khiếu nại',         3);

-- ============================================================
-- THÀNH VIÊN 1: users, students, buildings
-- ============================================================

CREATE TABLE users (
    user_id      INT           NOT NULL AUTO_INCREMENT,
    username     VARCHAR(50)   NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL                   COMMENT 'Bcrypt hash',
    email        VARCHAR(100)  NOT NULL UNIQUE,
    full_name    VARCHAR(100)  NOT NULL,
    phone        VARCHAR(15)   DEFAULT NULL UNIQUE,        -- [d] UNIQUE
    role         ENUM('admin','staff','student') NOT NULL DEFAULT 'student',
    avatar_url   VARCHAR(500)  DEFAULT NULL               COMMENT 'Cloud Storage URL',
    status_code  VARCHAR(50)   NOT NULL DEFAULT 'active'  COMMENT 'Ref: statuses(user,code)',
    last_login   DATETIME      DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by   INT           DEFAULT NULL               COMMENT 'user_id người tạo — [f]',
    updated_by   INT           DEFAULT NULL               COMMENT 'user_id người sửa — [f]',
    PRIMARY KEY (user_id),
    INDEX idx_role        (role),
    INDEX idx_status_code (status_code)
) ENGINE=InnoDB COMMENT='Tài khoản hệ thống';

CREATE TABLE students (
    student_id     INT           NOT NULL AUTO_INCREMENT,
    user_id        INT           NOT NULL,
    student_code   VARCHAR(20)   NOT NULL UNIQUE           COMMENT 'MSSV',
    full_name      VARCHAR(100)  NOT NULL,
    date_of_birth  DATE          NOT NULL,
    gender         ENUM('male','female','other') NOT NULL,
    id_card        VARCHAR(20)   NOT NULL UNIQUE           COMMENT 'CCCD/CMND — [d] UNIQUE',
    faculty        VARCHAR(100)  NOT NULL,
    major          VARCHAR(100)  NOT NULL,
    intake_year    YEAR          NOT NULL,
    class_name     VARCHAR(30)   NOT NULL,
    hometown       VARCHAR(200)  DEFAULT NULL,
    address        VARCHAR(255)  DEFAULT NULL,
    parent_name    VARCHAR(100)  DEFAULT NULL,
    parent_phone   VARCHAR(15)   DEFAULT NULL UNIQUE,      -- [d] UNIQUE
    health_status  TEXT          DEFAULT NULL,
    status_code    VARCHAR(50)   NOT NULL DEFAULT 'active' COMMENT 'Ref: statuses(student,code)',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by     INT           DEFAULT NULL,             -- [f] audit
    updated_by     INT           DEFAULT NULL,             -- [f] audit
    PRIMARY KEY (student_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_student_code (student_code),
    INDEX idx_faculty      (faculty),
    INDEX idx_status_code  (status_code)
) ENGINE=InnoDB COMMENT='Hồ sơ sinh viên';

CREATE TABLE buildings (
    building_id    INT           NOT NULL AUTO_INCREMENT,
    building_code  VARCHAR(10)   NOT NULL UNIQUE,
    building_name  VARCHAR(100)  NOT NULL,
    gender_type    ENUM('male','female','mixed') NOT NULL,
    total_floors   TINYINT       NOT NULL DEFAULT 1,
    total_rooms    SMALLINT      NOT NULL DEFAULT 0,
    manager_name   VARCHAR(100)  DEFAULT NULL,
    manager_phone  VARCHAR(15)   DEFAULT NULL,
    description    TEXT          DEFAULT NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by     INT           DEFAULT NULL,             -- [f] audit
    updated_by     INT           DEFAULT NULL,             -- [f] audit
    PRIMARY KEY (building_id)
) ENGINE=InnoDB COMMENT='Danh sách tòa nhà KTX';

-- ============================================================
-- THÀNH VIÊN 2: room_types(NEW), rooms, room_registrations, contracts
-- ============================================================

-- [a] Tách bảng room_types — không còn ENUM trong rooms
CREATE TABLE room_types (
    type_id          INT            NOT NULL AUTO_INCREMENT,
    type_code        VARCHAR(20)    NOT NULL UNIQUE   COMMENT 'vd: STANDARD_4, VIP_2',
    type_name        VARCHAR(100)   NOT NULL          COMMENT 'vd: Phòng 4 người tiêu chuẩn',
    capacity         TINYINT        NOT NULL          COMMENT 'Sức chứa tối đa',
    price_per_month  DECIMAL(10,2)  NOT NULL          COMMENT 'Giá thuê/tháng hiện hành',
    area_m2          DECIMAL(5,2)   DEFAULT NULL,
    default_facilities JSON         DEFAULT NULL      COMMENT 'Tiện nghi mặc định của loại phòng',
    description      TEXT           DEFAULT NULL,
    is_active        TINYINT(1)     NOT NULL DEFAULT 1,
    created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by       INT            DEFAULT NULL,
    updated_by       INT            DEFAULT NULL,
    PRIMARY KEY (type_id)
) ENGINE=InnoDB COMMENT='Loại phòng — tách riêng để cập nhật giá không cần ALTER';

CREATE TABLE rooms (
    room_id           INT           NOT NULL AUTO_INCREMENT,
    building_id       INT           NOT NULL,
    type_id           INT           NOT NULL,              -- [a] FK → room_types
    room_number       VARCHAR(10)   NOT NULL,
    floor             TINYINT       NOT NULL,
    current_occupancy TINYINT       NOT NULL DEFAULT 0,
    extra_facilities  JSON          DEFAULT NULL           COMMENT 'Tiện nghi bổ sung riêng phòng này',
    status_code       VARCHAR(50)   NOT NULL DEFAULT 'available',
    notes             TEXT          DEFAULT NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by        INT           DEFAULT NULL,          -- [f]
    updated_by        INT           DEFAULT NULL,          -- [f]
    PRIMARY KEY (room_id),
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    FOREIGN KEY (type_id)     REFERENCES room_types(type_id),
    UNIQUE KEY uq_room (building_id, room_number),
    INDEX idx_status_code (status_code)
) ENGINE=InnoDB COMMENT='Phòng trong từng tòa nhà';

CREATE TABLE room_registrations (
    registration_id   INT      NOT NULL AUTO_INCREMENT,
    student_id        INT      NOT NULL,
    room_id           INT      NOT NULL,
    semester          VARCHAR(20) NOT NULL,
    requested_date    DATE     NOT NULL,
    preferred_roommate INT     DEFAULT NULL,
    reason            TEXT     DEFAULT NULL,
    status_code       VARCHAR(50) NOT NULL DEFAULT 'pending',
    reviewed_by       INT      DEFAULT NULL,
    reviewed_at       DATETIME DEFAULT NULL,
    reject_reason     TEXT     DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by        INT      DEFAULT NULL,               -- [f]
    updated_by        INT      DEFAULT NULL,               -- [f]
    PRIMARY KEY (registration_id),
    FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id)     REFERENCES rooms(room_id)       ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)       ON DELETE SET NULL,
    INDEX idx_semester   (semester),
    INDEX idx_status_code (status_code)
) ENGINE=InnoDB COMMENT='Đăng ký phòng của sinh viên';

CREATE TABLE contracts (
    contract_id       INT           NOT NULL AUTO_INCREMENT,
    contract_code     VARCHAR(30)   NOT NULL UNIQUE,
    student_id        INT           NOT NULL,
    room_id           INT           NOT NULL,
    registration_id   INT           DEFAULT NULL,
    -- [b] snapshot giá tại thời điểm ký hợp đồng (không phụ thuộc room_types thay đổi sau)
    monthly_fee_snapshot DECIMAL(10,2) NOT NULL            COMMENT 'Giá tháng chốt lúc ký HĐ',
    start_date        DATE          NOT NULL,
    end_date          DATE          NOT NULL,
    deposit_amount    DECIMAL(10,2) NOT NULL DEFAULT 0,
    deposit_paid      TINYINT(1)    NOT NULL DEFAULT 0,
    deposit_paid_date DATE          DEFAULT NULL,
    terms             TEXT          DEFAULT NULL,
    signed_date       DATE          DEFAULT NULL,
    status_code       VARCHAR(50)   NOT NULL DEFAULT 'draft',
    termination_reason TEXT         DEFAULT NULL,
    terminated_at     DATETIME      DEFAULT NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by        INT           DEFAULT NULL,          -- [f]
    updated_by        INT           DEFAULT NULL,          -- [f]
    PRIMARY KEY (contract_id),
    FOREIGN KEY (student_id)      REFERENCES students(student_id)               ON DELETE CASCADE,
    FOREIGN KEY (room_id)         REFERENCES rooms(room_id)                     ON DELETE CASCADE,
    FOREIGN KEY (registration_id) REFERENCES room_registrations(registration_id) ON DELETE SET NULL,
    INDEX idx_status_code (status_code),
    INDEX idx_start_date  (start_date),
    INDEX idx_end_date    (end_date)
) ENGINE=InnoDB COMMENT='Hợp đồng thuê phòng';

-- [b] Trigger: đảm bảo room_id trong contracts khớp với room_id trong registration
DELIMITER $$
CREATE TRIGGER trg_contract_room_check
BEFORE INSERT ON contracts
FOR EACH ROW
BEGIN
    DECLARE reg_room_id INT;
    IF NEW.registration_id IS NOT NULL THEN
        SELECT room_id INTO reg_room_id
        FROM room_registrations
        WHERE registration_id = NEW.registration_id;
        IF reg_room_id IS NOT NULL AND reg_room_id <> NEW.room_id THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'room_id trong contracts phải khớp với room_id trong room_registrations';
        END IF;
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- THÀNH VIÊN 3: invoices, utility_readings, violation_records
-- ============================================================

CREATE TABLE utility_readings (
    reading_id         INT            NOT NULL AUTO_INCREMENT,
    room_id            INT            NOT NULL,
    reading_month      CHAR(7)        NOT NULL               COMMENT 'YYYY-MM',
    -- Điện
    electricity_prev   DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chỉ số đầu kỳ (kWh)',
    electricity_curr   DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chỉ số cuối kỳ (kWh)',
    electricity_used   DECIMAL(10,2)  GENERATED ALWAYS AS (electricity_curr - electricity_prev) STORED,
    electricity_rate   DECIMAL(8,2)   NOT NULL DEFAULT 3500  COMMENT 'Đơn giá điện tại thời điểm ghi (đ/kWh) — [c] snapshot',
    electricity_amount DECIMAL(10,2)  GENERATED ALWAYS AS ((electricity_curr - electricity_prev) * electricity_rate) STORED,
    -- Nước
    water_prev         DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chỉ số đầu kỳ (m³)',
    water_curr         DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chỉ số cuối kỳ (m³)',
    water_used         DECIMAL(10,2)  GENERATED ALWAYS AS (water_curr - water_prev) STORED,
    water_rate         DECIMAL(8,2)   NOT NULL DEFAULT 15000 COMMENT 'Đơn giá nước tại thời điểm ghi (đ/m³) — [c] snapshot',
    water_amount       DECIMAL(10,2)  GENERATED ALWAYS AS ((water_curr - water_prev) * water_rate) STORED,
    -- [f] Cloud Storage cho ảnh chụp đồng hồ
    image_url          VARCHAR(500)   DEFAULT NULL           COMMENT 'URL Cloud Storage (S3/Cloudinary) — [f]',
    storage_provider   VARCHAR(50)    DEFAULT NULL           COMMENT 'vd: cloudinary, s3, local',
    notes              TEXT           DEFAULT NULL,
    recorded_by        INT            DEFAULT NULL,
    recorded_at        DATETIME       DEFAULT NULL,
    created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by         INT            DEFAULT NULL,          -- [f]
    updated_by         INT            DEFAULT NULL,          -- [f]
    PRIMARY KEY (reading_id),
    FOREIGN KEY (room_id)     REFERENCES rooms(room_id)  ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)  ON DELETE SET NULL,
    UNIQUE KEY uq_room_month (room_id, reading_month),
    INDEX idx_reading_month  (reading_month)
) ENGINE=InnoDB COMMENT='Chỉ số điện/nước hàng tháng';

CREATE TABLE invoices (
    invoice_id          INT            NOT NULL AUTO_INCREMENT,
    invoice_code        VARCHAR(30)    NOT NULL UNIQUE,
    contract_id         INT            NOT NULL,
    student_id          INT            NOT NULL,
    billing_month       CHAR(7)        NOT NULL               COMMENT 'YYYY-MM',
    -- [c] Snapshot giá tại thời điểm xuất hóa đơn — không tính lại từ utility_readings
    room_fee            DECIMAL(10,2)  NOT NULL DEFAULT 0,
    electricity_fee     DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chốt từ utility_readings lúc xuất HĐ',
    electricity_kwh     DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Snapshot số kWh tiêu thụ',
    electricity_rate    DECIMAL(8,2)   NOT NULL DEFAULT 0     COMMENT 'Snapshot đơn giá điện lúc xuất HĐ',
    water_fee           DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Chốt từ utility_readings lúc xuất HĐ',
    water_m3            DECIMAL(10,2)  NOT NULL DEFAULT 0     COMMENT 'Snapshot số m³ tiêu thụ',
    water_rate          DECIMAL(8,2)   NOT NULL DEFAULT 0     COMMENT 'Snapshot đơn giá nước lúc xuất HĐ',
    service_fee         DECIMAL(10,2)  NOT NULL DEFAULT 0,
    penalty_fee         DECIMAL(10,2)  NOT NULL DEFAULT 0,
    discount            DECIMAL(10,2)  NOT NULL DEFAULT 0,
    total_amount        DECIMAL(10,2)  NOT NULL,
    due_date            DATE           NOT NULL,
    paid_amount         DECIMAL(10,2)  NOT NULL DEFAULT 0,
    paid_date           DATE           DEFAULT NULL,
    payment_method      ENUM('cash','bank_transfer','momo','vnpay','other') DEFAULT NULL,
    payment_note        VARCHAR(255)   DEFAULT NULL,
    status_code         VARCHAR(50)    NOT NULL DEFAULT 'unpaid',
    issued_by           INT            DEFAULT NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT            DEFAULT NULL,          -- [f]
    updated_by          INT            DEFAULT NULL,          -- [f]
    PRIMARY KEY (invoice_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES students(student_id)   ON DELETE CASCADE,
    FOREIGN KEY (issued_by)   REFERENCES users(user_id)         ON DELETE SET NULL,
    INDEX idx_billing_month (billing_month),
    INDEX idx_status_code   (status_code),
    INDEX idx_due_date      (due_date)
) ENGINE=InnoDB COMMENT='Hóa đơn tiền phòng & dịch vụ — giá chốt tại thời điểm xuất';

CREATE TABLE violation_records (
    violation_id     INT            NOT NULL AUTO_INCREMENT,
    student_id       INT            NOT NULL,
    room_id          INT            DEFAULT NULL,
    violation_type   ENUM(
        'noise','curfew','unauthorized_guest','property_damage',
        'hygiene','cooking','fire_safety','fighting','substance','other'
    ) NOT NULL,
    description      TEXT           NOT NULL,
    violation_date   DATE           NOT NULL,
    violation_time   TIME           DEFAULT NULL,
    -- [f] Cloud Storage cho bằng chứng
    evidence_url     VARCHAR(500)   DEFAULT NULL             COMMENT 'URL Cloud Storage — [f]',
    storage_provider VARCHAR(50)    DEFAULT NULL             COMMENT 'vd: cloudinary, s3',
    severity         ENUM('minor','moderate','serious','critical') NOT NULL DEFAULT 'minor',
    penalty          TEXT           DEFAULT NULL,
    penalty_fee      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    penalty_fee_paid TINYINT(1)     NOT NULL DEFAULT 0,
    reported_by      INT            DEFAULT NULL,
    status_code      VARCHAR(50)    NOT NULL DEFAULT 'open',
    resolved_note    TEXT           DEFAULT NULL,
    resolved_at      DATETIME       DEFAULT NULL,
    created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by       INT            DEFAULT NULL,            -- [f]
    updated_by       INT            DEFAULT NULL,            -- [f]
    PRIMARY KEY (violation_id),
    FOREIGN KEY (student_id)  REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id)     REFERENCES rooms(room_id)       ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(user_id)       ON DELETE SET NULL,
    INDEX idx_student       (student_id),
    INDEX idx_violation_date (violation_date),
    INDEX idx_severity      (severity),
    INDEX idx_status_code   (status_code)
) ENGINE=InnoDB COMMENT='Ghi nhận vi phạm nội quy';

-- ============================================================
-- DỮ LIỆU MẪU
-- ============================================================

-- users
INSERT INTO users (username, password, email, full_name, phone, role, created_by) VALUES
('admin01',        '$2b$12$KIXHs0cBVCjPmVHzK9.gAeHASHED', 'admin@ktx.edu.vn',          'Nguyễn Văn Admin',       '0901000001', 'admin',   NULL),
('staff_linhnh',   '$2b$12$abc123hashedpassword1',          'linhnh@ktx.edu.vn',          'Ngô Hải Linh',           '0901000002', 'staff',   1),
('staff_tungbt',   '$2b$12$abc123hashedpassword2',          'tungbt@ktx.edu.vn',          'Bùi Thành Tùng',         '0901000003', 'staff',   1),
('staff_huongptt', '$2b$12$abc123hashedpassword3',          'huongptt@ktx.edu.vn',        'Phan Thị Thu Hương',     '0901000004', 'staff',   1),
('sv_anhltm',      '$2b$12$abc123hashedpassword4',          'anhltm@student.edu.vn',      'Lê Thị Minh Anh',        '0912000001', 'student', 2),
('sv_hienntb',     '$2b$12$abc123hashedpassword5',          'hienntb@student.edu.vn',     'Nguyễn Thị Bích Hiền',   '0912000002', 'student', 2),
('sv_khoavd',      '$2b$12$abc123hashedpassword6',          'khoavd@student.edu.vn',      'Vũ Đức Khoa',            '0912000003', 'student', 3),
('sv_namtq',       '$2b$12$abc123hashedpassword7',          'namtq@student.edu.vn',       'Trần Quốc Nam',          '0912000004', 'student', 3),
('sv_thaobnp',     '$2b$12$abc123hashedpassword8',          'thaobnp@student.edu.vn',     'Bùi Ngọc Phương Thảo',   '0912000005', 'student', 2),
('sv_dungnv',      '$2b$12$abc123hashedpassword9',          'dungnv@student.edu.vn',      'Ngô Việt Dũng',          '0912000006', 'student', 3);

-- students
INSERT INTO students (user_id, student_code, full_name, date_of_birth, gender, id_card, faculty, major, intake_year, class_name, hometown, parent_name, parent_phone, created_by) VALUES
(5,  '2151012001', 'Lê Thị Minh Anh',        '2003-04-15', 'female', '079203000101', 'Công nghệ thông tin', 'Kỹ thuật phần mềm',    2021, '21SE01', 'Hà Nội',    'Lê Văn Bình',      '0981000001', 2),
(6,  '2151012002', 'Nguyễn Thị Bích Hiền',   '2003-07-22', 'female', '079203000102', 'Công nghệ thông tin', 'Hệ thống thông tin',   2021, '21IS02', 'Nam Định',  'Nguyễn Văn Cường', '0981000002', 2),
(7,  '2251023001', 'Vũ Đức Khoa',             '2004-01-30', 'male',   '034204000201', 'Kinh tế',             'Kế toán',              2022, '22AC01', 'Thái Bình', 'Vũ Thị Dung',      '0981000003', 3),
(8,  '2251023002', 'Trần Quốc Nam',           '2004-09-05', 'male',   '034204000202', 'Kinh tế',             'Quản trị kinh doanh',  2022, '22BA03', 'Thanh Hóa', 'Trần Văn Hùng',    '0981000004', 3),
(9,  '2351034001', 'Bùi Ngọc Phương Thảo',   '2005-03-18', 'female', '025205000301', 'Ngoại ngữ',           'Ngôn ngữ Anh',         2023, '23EN01', 'Hải Phòng', 'Bùi Thị Lan',      '0981000005', 2),
(10, '2351034002', 'Ngô Việt Dũng',           '2005-11-27', 'male',   '025205000302', 'Điện - Điện tử',      'Kỹ thuật điện',        2023, '23EE02', 'Ninh Bình', 'Ngô Văn Minh',     '0981000006', 3);

-- buildings
INSERT INTO buildings (building_code, building_name, gender_type, total_floors, total_rooms, manager_name, manager_phone, description, created_by) VALUES
('A', 'Nhà A - Nữ sinh',      'female', 7, 80, 'Phan Thị Thu Hương', '0901000004', 'Tòa nhà dành riêng cho nữ sinh, bảo vệ 24/7', 1),
('B', 'Nhà B - Nam sinh',     'male',   7, 80, 'Bùi Thành Tùng',     '0901000003', 'Tòa nhà dành riêng cho nam sinh, có phòng gym', 1),
('C', 'Nhà C - Hỗn hợp',     'mixed',  5, 60, 'Ngô Hải Linh',       '0901000002', 'Tòa nhà hỗn hợp, mỗi tầng chia khu riêng', 1),
('D', 'Nhà D - Sau đại học',  'mixed',  4, 40, 'Ngô Hải Linh',       '0901000002', 'Dành cho học viên cao học và nghiên cứu sinh', 1);

-- [a] room_types — thay thế ENUM trong rooms
INSERT INTO room_types (type_code, type_name, capacity, price_per_month, area_m2, default_facilities, created_by) VALUES
('STD_2', 'Phòng 2 người tiêu chuẩn', 2, 700000, 16.00, '{"ac":true,"fan":true,"private_wc":true,"locker":true}',  1),
('STD_4', 'Phòng 4 người tiêu chuẩn', 4, 400000, 24.00, '{"ac":true,"fan":true,"private_wc":false,"locker":true}', 1),
('STD_6', 'Phòng 6 người tiêu chuẩn', 6, 250000, 36.00, '{"ac":false,"fan":true,"private_wc":false,"locker":true}',1),
('ECO_4', 'Phòng 4 người kinh tế',    4, 300000, 24.00, '{"ac":false,"fan":true,"private_wc":false,"locker":true}',1);

-- rooms (type_id thay cho room_type ENUM)
INSERT INTO rooms (building_id, type_id, room_number, floor, current_occupancy, status_code, created_by) VALUES
(1, 2, '101', 1, 4, 'full',        2),  -- Nhà A, 4 người TC
(1, 2, '201', 2, 2, 'available',   2),  -- Nhà A, 4 người TC
(1, 1, '301', 3, 2, 'full',        2),  -- Nhà A, 2 người TC
(2, 4, '101', 1, 3, 'available',   3),  -- Nhà B, 4 người KT
(2, 3, '201', 2, 6, 'full',        3),  -- Nhà B, 6 người TC
(2, 1, '401', 4, 1, 'available',   3),  -- Nhà B, 2 người TC
(3, 2, '201', 2, 4, 'full',        2),  -- Nhà C, 4 người TC
(3, 1, '301', 3, 0, 'maintenance', 2);  -- Nhà C, 2 người TC

-- room_registrations
INSERT INTO room_registrations (student_id, room_id, semester, requested_date, reason, status_code, reviewed_by, reviewed_at, created_by) VALUES
(1, 1, 'HK2-2024-2025', '2025-01-05', 'Nhà xa trường trên 20km, hộ nghèo',     'approved', 2, '2025-01-08', 2),
(2, 1, 'HK2-2024-2025', '2025-01-05', 'Nhà xa trường trên 20km',               'approved', 2, '2025-01-08', 2),
(3, 4, 'HK2-2024-2025', '2025-01-06', 'Sinh viên ngoại tỉnh',                  'approved', 3, '2025-01-09', 3),
(4, 4, 'HK2-2024-2025', '2025-01-06', 'Sinh viên ngoại tỉnh, diện ưu tiên',    'approved', 3, '2025-01-09', 3),
(5, 2, 'HK2-2024-2025', '2025-01-07', 'Sinh viên ngoại tỉnh',                  'approved', 2, '2025-01-10', 2),
(6, 5, 'HK2-2024-2025', '2025-01-07', 'Sinh viên diện khó khăn',               'pending',  NULL, NULL,       2);

-- contracts (monthly_fee_snapshot chốt từ room_types lúc ký)
INSERT INTO contracts (contract_code, student_id, room_id, registration_id, monthly_fee_snapshot, start_date, end_date, deposit_amount, deposit_paid, deposit_paid_date, status_code, signed_date, created_by) VALUES
('HD-2025-00001', 1, 1, 1, 400000, '2025-02-01', '2025-07-31', 800000, 1, '2025-01-15', 'active', '2025-01-15', 2),
('HD-2025-00002', 2, 1, 2, 400000, '2025-02-01', '2025-07-31', 800000, 1, '2025-01-15', 'active', '2025-01-15', 2),
('HD-2025-00003', 3, 4, 3, 300000, '2025-02-01', '2025-07-31', 600000, 1, '2025-01-16', 'active', '2025-01-16', 3),
('HD-2025-00004', 4, 4, 4, 300000, '2025-02-01', '2025-07-31', 600000, 1, '2025-01-16', 'active', '2025-01-16', 3),
('HD-2025-00005', 5, 2, 5, 400000, '2025-02-01', '2025-07-31', 800000, 1, '2025-01-17', 'active', '2025-01-17', 2);

-- utility_readings (đơn giá chốt tại thời điểm ghi — [c] snapshot)
INSERT INTO utility_readings (room_id, reading_month, electricity_prev, electricity_curr, electricity_rate, water_prev, water_curr, water_rate, storage_provider, recorded_by, recorded_at, created_by) VALUES
(1, '2025-03', 1200.0, 1345.0, 3500, 50.0, 57.5, 15000, 'cloudinary', 2, '2025-04-01 09:00:00', 2),
(4, '2025-03',  800.0,  930.0, 3500, 32.0, 38.0, 15000, 'cloudinary', 3, '2025-04-01 09:30:00', 3),
(2, '2025-03',  600.0,  720.0, 3500, 24.0, 29.5, 15000, 'cloudinary', 2, '2025-04-01 10:00:00', 2),
(1, '2025-04', 1345.0, 1498.0, 3500, 57.5, 65.0, 15000, 'cloudinary', 2, '2025-05-01 09:00:00', 2),
(4, '2025-04',  930.0, 1072.0, 3500, 38.0, 44.5, 15000, 'cloudinary', 3, '2025-05-01 09:30:00', 3),
(2, '2025-04',  720.0,  845.0, 3500, 29.5, 35.0, 15000, 'cloudinary', 2, '2025-05-01 10:00:00', 2);

-- invoices (snapshot đủ kWh, m³, đơn giá — [c])
INSERT INTO invoices (invoice_code, contract_id, student_id, billing_month,
    room_fee, electricity_fee, electricity_kwh, electricity_rate,
    water_fee, water_m3, water_rate,
    service_fee, penalty_fee, discount, total_amount,
    due_date, paid_amount, paid_date, payment_method, status_code, issued_by, created_by) VALUES
('HD-2025-03-001', 1, 1, '2025-03', 400000, 507500, 145.0, 3500, 112500, 7.5, 15000, 50000, 0,       0,      1070000, '2025-04-10', 1070000, '2025-04-08', 'bank_transfer', 'paid',    2, 2),
('HD-2025-03-002', 2, 2, '2025-03', 400000, 507500, 145.0, 3500, 112500, 7.5, 15000, 50000, 0,       0,      1070000, '2025-04-10', 1070000, '2025-04-09', 'momo',          'paid',    2, 2),
('HD-2025-03-003', 3, 3, '2025-03', 300000, 455000, 130.0, 3500,  90000, 6.0, 15000, 50000, 0,       0,       895000, '2025-04-10',  895000, '2025-04-07', 'bank_transfer', 'paid',    3, 3),
('HD-2025-03-004', 4, 4, '2025-03', 300000, 455000, 130.0, 3500,  90000, 6.0, 15000, 50000, 200000,  0,      1095000, '2025-04-10',       0,  NULL,         NULL,            'overdue', 3, 3),
('HD-2025-03-005', 5, 5, '2025-03', 400000, 420000, 120.0, 3500,  82500, 5.5, 15000, 50000, 0,       50000,   902500, '2025-04-10',  902500, '2025-04-10', 'vnpay',         'paid',    2, 2),
('HD-2025-04-001', 1, 1, '2025-04', 400000, 535500, 153.0, 3500, 112500, 7.5, 15000, 50000, 0,       0,      1098000, '2025-05-10',       0,  NULL,         NULL,            'unpaid',  2, 2),
('HD-2025-04-002', 2, 2, '2025-04', 400000, 535500, 153.0, 3500, 112500, 7.5, 15000, 50000, 0,       0,      1098000, '2025-05-10',       0,  NULL,         NULL,            'unpaid',  2, 2),
('HD-2025-04-003', 3, 3, '2025-04', 300000, 497000, 142.0, 3500,  97500, 6.5, 15000, 50000, 0,       0,       944500, '2025-05-10',       0,  NULL,         NULL,            'unpaid',  3, 3),
('HD-2025-04-004', 4, 4, '2025-04', 300000, 497000, 142.0, 3500,  97500, 6.5, 15000, 50000, 0,       0,       944500, '2025-05-10',       0,  NULL,         NULL,            'unpaid',  3, 3),
('HD-2025-04-005', 5, 5, '2025-04', 400000, 437500, 125.0, 3500,  82500, 5.5, 15000, 50000, 0,       50000,   920000, '2025-05-10',       0,  NULL,         NULL,            'unpaid',  2, 2);

-- violation_records
INSERT INTO violation_records (student_id, room_id, violation_type, description, violation_date, violation_time, severity, penalty, penalty_fee, reported_by, status_code, resolved_note, resolved_at, created_by) VALUES
(4, 4, 'noise',               'Gây ồn ào sau 23h, bật nhạc to ảnh hưởng phòng bên cạnh',          '2025-03-12', '23:30:00', 'minor',    'Cảnh cáo lần 1, lập biên bản nhắc nhở',             0,      3, 'resolved', 'Sinh viên đã cam kết không tái phạm',           '2025-03-13 08:00:00', 3),
(4, 4, 'curfew',              'Về phòng lúc 01:15 sáng, vi phạm giờ giới nghiêm',                  '2025-03-25', '01:15:00', 'moderate', 'Cảnh cáo lần 2, thông báo phụ huynh, phạt tiền',   200000, 3, 'resolved', 'Đã thu phạt và thông báo gia đình',             '2025-03-26 09:00:00', 3),
(3, 4, 'hygiene',             'Không vệ sinh khu vực chung, rác để tràn lan trước cửa phòng',      '2025-03-18', '14:00:00', 'minor',    'Yêu cầu dọn dẹp ngay, nhắc nhở bằng văn bản',      0,      3, 'resolved', 'Đã khắc phục xong trong ngày',                  '2025-03-18 16:30:00', 3),
(6, 5, 'unauthorized_guest',  'Đưa người lạ không phải sinh viên KTX vào phòng qua đêm',           '2025-04-02', '22:00:00', 'serious',  'Cảnh cáo chính thức, phạt tiền, ghi vào hồ sơ',    0,      2, 'open',     NULL, NULL, 2),
(1, 1, 'cooking',             'Nấu ăn bằng bếp điện trong phòng ngủ, nguy cơ cháy nổ',            '2025-04-10', '12:30:00', 'moderate', 'Tịch thu bếp điện, phạt tiền, nhắc nhở an toàn',   0,      2, 'open',     NULL, NULL, 2),
(2, 1, 'noise',               'Tranh cãi to tiếng với bạn cùng phòng vào buổi sáng',               '2025-04-15', '07:45:00', 'minor',    'Hòa giải tại chỗ, nhắc nhở cả hai bên',             0,      2, 'resolved', 'Hai bên đã hòa giải, không cần xử lý thêm',     '2025-04-15 09:00:00', 2);

-- ============================================================
-- VIEWS
-- ============================================================

CREATE OR REPLACE VIEW v_student_room_info AS
SELECT
    s.student_code,
    s.full_name                         AS student_name,
    s.faculty,
    rt.type_name                        AS room_type,
    b.building_name,
    r.room_number,
    c.monthly_fee_snapshot              AS monthly_fee,
    c.contract_code,
    c.start_date,
    c.end_date,
    c.status_code                       AS contract_status
FROM students s
JOIN contracts c    ON c.student_id  = s.student_id AND c.status_code = 'active'
JOIN rooms r        ON r.room_id     = c.room_id
JOIN room_types rt  ON rt.type_id    = r.type_id
JOIN buildings b    ON b.building_id = r.building_id;

CREATE OR REPLACE VIEW v_unpaid_invoices AS
SELECT
    s.student_code,
    s.full_name,
    COUNT(i.invoice_id)                        AS total_invoices,
    SUM(i.total_amount - i.paid_amount)        AS total_debt,
    MAX(i.due_date)                            AS latest_due_date
FROM invoices i
JOIN students s ON s.student_id = i.student_id
WHERE i.status_code IN ('unpaid','partial','overdue')
GROUP BY s.student_id, s.student_code, s.full_name;

CREATE OR REPLACE VIEW v_violation_summary AS
SELECT
    s.student_code,
    s.full_name,
    COUNT(v.violation_id)                                           AS total_violations,
    SUM(CASE WHEN v.status_code = 'open' THEN 1 ELSE 0 END)        AS unresolved,
    SUM(v.penalty_fee)                                              AS total_penalty_fee,
    MAX(v.violation_date)                                           AS last_violation_date
FROM violation_records v
JOIN students s ON s.student_id = v.student_id
GROUP BY s.student_id, s.student_code, s.full_name;

CREATE OR REPLACE VIEW v_room_occupancy AS
SELECT
    b.building_name,
    r.room_number,
    rt.type_name,
    rt.capacity,
    r.current_occupancy,
    (rt.capacity - r.current_occupancy)         AS available_slots,
    rt.price_per_month,
    r.status_code
FROM rooms r
JOIN room_types rt  ON rt.type_id    = r.type_id
JOIN buildings b    ON b.building_id = r.building_id;

-- ============================================================
-- END OF SCRIPT  (v2 — revised per lecturer feedback)
-- ============================================================
