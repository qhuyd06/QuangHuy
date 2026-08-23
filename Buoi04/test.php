<?php
session_start();

// --- 1. KẾT NỐI SQL SERVER QUA ODBC (ĐÃ CẤU HÌNH UTF-8 TIẾNG VIỆT) ---
$serverName = "localhost"; 
$database   = "quanlylichhen"; // Hoặc "QuanLyDanhGia" tùy theo tên Database của bạn
$username   = "sa";
$password   = "123456";

try {
    // Chuỗi DSN có thêm ClientCharset=UTF-8 để sửa triệt để lỗi phông chữ
    $dsn = "odbc:Driver={ODBC Driver 17 for SQL Server};Server=$serverName;Database=$database;TrustServerCertificate=yes;ClientCharset=UTF-8;";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    try {
        $dsn = "odbc:Driver={SQL Server};Server=$serverName;Database=$database;ClientCharset=UTF-8;";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $ex) {
        die("Lỗi kết nối SQL Server: " . $ex->getMessage());
    }
}

// Mảng lưu lỗi và giữ lại giá trị cũ khi form lỗi
$errors = [];
$old = [
    'lecturer_name' => '',
    'rating'        => 0,
    'comment'       => ''
];

$success_message = '';

// --- 2. HÀM PHÂN LOẠI ĐÁNH GIÁ ---
function phanLoaiDanhGia($rating) {
    if ($rating == 5) return '<span class="badge badge-excellent"><span class="dot"></span> Xuất sắc</span>';
    if ($rating >= 4) return '<span class="badge badge-good"><span class="dot"></span> Tốt</span>';
    if ($rating == 3) return '<span class="badge badge-normal"><span class="dot"></span> Bình thường</span>';
    return '<span class="badge badge-poor"><span class="dot"></span> Cần cải thiện</span>';
}

// --- 3. CHỨC NĂNG XÓA PHẢN HỒI ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // ĐÚNG: Phải gọi $pdo->prepare(...)
    $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['success_message'] = "Xóa phản hồi thành công!";

    // Chuyển hướng về lại trang chính để làm sạch URL
    header("Location: test.php");
    exit();
}

// --- 4. XỬ LÝ LƯU PHẢN HỒI MỚI (LƯU UNICODE N-VARCHAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lecturer_name = trim($_POST['lecturer_name'] ?? '');
    $rating        = intval($_POST['rating'] ?? 0);
    $comment       = trim($_POST['comment'] ?? '');

    $old['lecturer_name'] = $lecturer_name;
    $old['rating']        = $rating;
    $old['comment']       = $comment;

    // Validate Tên giảng viên
    $allowed_lecturers = [
        'Nguyễn Hoàng Nam',
        'Nguyễn Thị Lan',
        'Trần Thị Hương',
        'Lê Minh Anh',
        'Phạm Thu Hà'
    ];
    if (empty($lecturer_name)) {
        $errors['lecturer_name'] = "Vui lòng chọn tên Giảng viên!";
    } elseif (!in_array($lecturer_name, $allowed_lecturers)) {
        $errors['lecturer_name'] = "Tên Giảng viên không hợp lệ!";
    }

    // Validate Số sao
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = "Vui lòng chọn mức độ đánh giá từ 1 đến 5 sao!";
    }

    // Validate Nhận xét
    if (empty($comment)) {
        $errors['comment'] = "Vui lòng nhập nội dung nhận xét!";
    } elseif (mb_strlen($comment, 'UTF-8') < 10) {
        $errors['comment'] = "Nội dung nhận xét quá ngắn (tối thiểu 10 ký tự)!";
    } elseif (mb_strlen($comment, 'UTF-8') > 500) {
        $errors['comment'] = "Nội dung nhận xét quá dài (tối đa 500 ký tự)!";
    }

    // Nếu không có lỗi -> Thêm vào SQL Server (Đã ép kiểu tiếng Việt UTF-16LE)
    if (empty($errors)) {
    $stmt = $pdo->prepare("INSERT INTO feedbacks (lecturer_name, rating, comment, created_at) VALUES (?, ?, ?, GETDATE())");
    $stmt->execute([$lecturer_name, $rating, $comment]);

    $_SESSION['success_message'] = "Gửi phản hồi thành công!";
    $current_page = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $current_page);
    exit();
}
}

// --- 5. LẤY DANH SÁCH DỮ LIỆU ---
$stmt = $pdo->query("SELECT id, lecturer_name, rating, comment, created_at FROM feedbacks ORDER BY created_at DESC");
$feedbacks = $stmt->fetchAll();

// Chuyển mã hiển thị UTF-8 từ UTF-16LE / CP1252 của SQL Server
foreach ($feedbacks as &$fb) {
    if (!empty($fb['lecturer_name'])) {
        $fb['lecturer_name'] = @mb_convert_encoding($fb['lecturer_name'], 'UTF-8', 'UTF-16LE, CP1252, ISO-8859-1');
    }
    if (!empty($fb['comment'])) {
        $fb['comment'] = @mb_convert_encoding($fb['comment'], 'UTF-8', 'UTF-16LE, CP1252, ISO-8859-1');
    }
}
unset($fb);

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đánh Giá Tư Vấn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #fff1f2;
            color: #1e293b;
            padding: 0.75rem;
        }
        @media (min-width: 768px) { body { padding: 1.5rem; } }

        .container { max-width: 1152px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.25rem; }
        
        .card { background: #ffffff; padding: 1.25rem; border-radius: 1rem; border: 1px solid #fecdd3; box-shadow: 0 1px 3px 0 rgba(225, 29, 72, 0.05); }
        .header-card { background: #ffffff; padding: 1.25rem 1.5rem; border-radius: 1rem; border: 1px solid #fecdd3; box-shadow: 0 1px 3px 0 rgba(225, 29, 72, 0.05); }
        .header-title-box { display: flex; align-items: center; gap: 0.75rem; }
        .header-icon { font-size: 1.75rem; color: #be123c; }
        .header-title { font-size: 1.375rem; font-weight: 800; color: #881337; }
        .header-subtitle { color: #9f1239; font-size: 0.8125rem; margin-top: 0.25rem; opacity: 0.8; }

        .alert { padding: 0.875rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

        .main-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 1024px) { .main-grid { grid-template-columns: 1fr 2fr; } }

        .section-title { font-size: 0.9375rem; font-weight: 700; color: #881337; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .icon-theme { color: #be123c; }

        .space-y-4 > * + * { margin-top: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.375rem; }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #4c0519; }
        .text-required { color: #e11d48; }
        .form-control { width: 100%; font-size: 0.75rem; padding: 0.625rem; border: 1px solid #fecdd3; border-radius: 0.75rem; background: #fff1f2; outline: none; }
        .form-control:focus { border-color: #be123c; box-shadow: 0 0 0 2px rgba(225, 29, 72, 0.2); }
        .form-control.is-invalid { border-color: #be123c; background: #fff5f5; }
        .error-text { font-size: 0.6875rem; color: #be123c; font-weight: 600; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.125rem; }

        .star-rating { background: #fff1f2; padding: 0.5rem; border: 1px solid #fecdd3; border-radius: 0.75rem; display: flex; flex-direction: row-reverse; justify-content: center; gap: 0.25rem; }
        .star-rating.is-invalid { border-color: #be123c; background: #fff5f5; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 1.75rem; color: #fca5a5; cursor: pointer; transition: color 0.2s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #e11d48; }

        .btn-submit { width: 100%; background: #be123c; color: #ffffff; font-weight: 600; font-size: 0.75rem; padding: 0.625rem 1rem; border-radius: 0.75rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 6px -1px rgba(225, 29, 72, 0.25); transition: background 0.2s; }
        .btn-submit:hover { background: #9f1239; }

        .table-container { border-radius: 0.75rem; border: 1px solid #fecdd3; overflow: hidden; }
        .custom-table { width: 100%; text-align: left; font-size: 0.75rem; border-collapse: collapse; table-layout: fixed; }
        .custom-table th { background: #ffe4e6; color: #881337; font-weight: 600; padding: 0.75rem 0.5rem; border-bottom: 1px solid #fecdd3; }
        .custom-table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #fff1f2; vertical-align: top; }
        .custom-table tr:hover { background: rgba(255, 241, 242, 0.6); }

        .col-stt { width: 2.5rem; text-align: center; }
        .col-lecturer { width: 8.5rem; }
        .col-rating { width: 5.5rem; }
        .col-badge { width: 6.5rem; }
        .col-action { width: 4.5rem; text-align: center; }

        .td-stt { color: #fda4af; font-weight: 500; text-align: center; }
        .td-lecturer { font-weight: 700; color: #4c0519; word-wrap: break-word; }
        .td-comment { color: #475569; font-style: italic; word-wrap: break-word; line-height: 1.4; }

        .stars-wrapper { color: #e11d48; font-size: 0.6875rem; display: inline-flex; gap: 0.125rem; }
        .star-empty { color: #fecdd3; }

        .btn-delete { display: inline-flex; align-items: center; gap: 0.25rem; color: #be123c; background: #ffe4e6; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.6875rem; text-decoration: none; border: none; }
        .btn-delete:hover { background: #fecdd3; }

        .badge { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 9999px; white-space: nowrap; }
        .badge-excellent { background: #d1fae5; color: #065f46; } .badge-excellent .dot { background: #10b981; }
        .badge-good { background: #ffe4e6; color: #9f1239; } .badge-good .dot { background: #f43f5e; }
        .badge-normal { background: #fef3c7; color: #92400e; } .badge-normal .dot { background: #f59e0b; }
        .badge-poor { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; } .badge-poor .dot { background: #e11d48; }
        .dot { width: 0.375rem; height: 0.375rem; border-radius: 50%; display: inline-block; }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="header-card">
            <div class="header-title-box">
                <div class="header-icon"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <h1 class="header-title">Cổng Sinh Viên: Gửi Phản Hồi & Đánh Giá</h1>
                    <p class="header-subtitle">Hệ thống tiếp nhận ý kiến tư vấn và đánh giá chất lượng từ sinh viên</p>
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= $success_message ?></span>
            </div>
        <?php endif; ?>

        <div class="main-grid">

            <!-- FORM NHẬP DỮ LIỆU -->
            <div class="card" style="height: fit-content;">
                <h2 class="section-title">
                    <i class="fa-solid fa-pen-to-square icon-theme"></i> Gửi đánh giá mới
                </h2>

                <form action="" method="POST" class="space-y-4" novalidate>
                    <div class="form-group">
                        <label for="lecturer_name" class="form-label">1. Tên Giảng viên <span class="text-required">*</span></label>
                        <select name="lecturer_name" id="lecturer_name" class="form-control <?= isset($errors['lecturer_name']) ? 'is-invalid' : '' ?>">
                            <option value="">-- Chọn Giảng viên --</option>
                            <option value="Nguyễn Hoàng Nam" <?= $old['lecturer_name'] === 'Nguyễn Hoàng Nam' ? 'selected' : '' ?>>Nguyễn Hoàng Nam</option>
                            <option value="Nguyễn Thị Lan" <?= $old['lecturer_name'] === 'Nguyễn Thị Lan' ? 'selected' : '' ?>>Nguyễn Thị Lan</option>
                            <option value="Trần Thị Hương" <?= $old['lecturer_name'] === 'Trần Thị Hương' ? 'selected' : '' ?>>Trần Thị Hương</option>
                            <option value="Lê Minh Anh" <?= $old['lecturer_name'] === 'Lê Minh Anh' ? 'selected' : '' ?>>Lê Minh Anh</option>
                            <option value="Phạm Thu Hà" <?= $old['lecturer_name'] === 'Phạm Thu Hà' ? 'selected' : '' ?>>Phạm Thu Hà</option>
                        </select>
                        <?php if (isset($errors['lecturer_name'])): ?>
                            <span class="error-text"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['lecturer_name'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">2. Chất lượng buổi tư vấn <span class="text-required">*</span></label>
                        <div class="star-rating <?= isset($errors['rating']) ? 'is-invalid' : '' ?>">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $old['rating'] == $i ? 'checked' : '' ?> />
                                <label for="star<?= $i ?>" title="<?= $i ?> sao">★</label>
                            <?php endfor; ?>
                        </div>
                        <?php if (isset($errors['rating'])): ?>
                            <span class="error-text"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['rating'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="comment" class="form-label">3. Nhận xét & Góp ý <span class="text-required">*</span></label>
                        <textarea name="comment" id="comment" rows="3" class="form-control <?= isset($errors['comment']) ? 'is-invalid' : '' ?>" placeholder="Chia sẻ trải nghiệm (từ 10 đến 500 ký tự)..."><?= htmlspecialchars($old['comment']) ?></textarea>
                        <?php if (isset($errors['comment'])): ?>
                            <span class="error-text"><i class="fa-solid fa-circle-exclamation"></i> <?= $errors['comment'] ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Gửi Phản Hồi
                    </button>
                </form>
            </div>

            <!-- BẢNG DỮ LIỆU HIỂN THỊ -->
            <div class="card space-y-4">
                <h2 class="section-title">
                    <i class="fa-solid fa-list-check icon-theme"></i> Danh sách phản hồi đã ghi nhận
                </h2>

                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th class="col-stt">STT</th>
                                <th class="col-lecturer">Giảng viên</th>
                                <th class="col-rating">Đánh giá</th>
                                <th class="col-badge">Phân loại</th>
                                <th>Nhận xét</th>
                                <th class="col-action">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbacks)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #fda4af; font-style: italic;">
                                        Chưa có phản hồi nào trong cơ sở dữ liệu.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedbacks as $index => $fb): ?>
                                    <tr>
                                        <td class="td-stt"><?= sprintf("%02d", $index + 1) ?></td>
                                        
                                        <td class="td-lecturer">
                                            <?= htmlspecialchars($fb['lecturer_name']) ?>
                                        </td>
                                        
                                        <td>
                                            <div class="stars-wrapper">
                                                <?= str_repeat('<i class="fa-solid fa-star"></i>', $fb['rating']) ?>
                                                <?= str_repeat('<i class="fa-regular fa-star star-empty"></i>', 5 - $fb['rating']) ?>
                                            </div>
                                        </td>
                                        
                                        <td><?= phanLoaiDanhGia($fb['rating']) ?></td>
                                        
                                        <td class="td-comment">
                                            "<?= !empty($fb['comment']) ? htmlspecialchars($fb['comment']) : 'Không có' ?>"
                                        </td>
                                        
                                        <td style="text-align: center;">
                                            <a href="?action=delete&id=<?= $fb['id'] ?>" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa phản hồi này không?');"
                                               class="btn-delete">
                                                <i class="fa-solid fa-trash-can"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>