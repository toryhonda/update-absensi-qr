<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PASSPHRASE_HASH_ABSENSI', '0f43e37fd7dcf76528a5811da79a975b72f06d5e5aaf00be9705cecb8fb67090');

$is_direct = basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__);

if ($is_direct) {
    $allowed_roles = ['admin', 'developer'];
    if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
        header("Location: index.php");
        exit;
    }
}

$is_developer = ($_SESSION['role'] ?? '') === 'developer';
$passphrase_unlocked = !empty($_SESSION['wa_passphrase_ok']);
$can_edit = $is_developer || $passphrase_unlocked;

include 'config.php';

$check_col = mysqli_query($conn, "SHOW COLUMNS FROM profil_sekolah LIKE 'wa_phone_display'");
if ($check_col && mysqli_num_rows($check_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE profil_sekolah ADD COLUMN wa_phone_display VARCHAR(20) DEFAULT NULL AFTER wa_business_account_id");
}

$pesan_notif = $_SESSION['wa_notif'] ?? "";
unset($_SESSION['wa_notif']);

function kirimPesanWhatsApp($tujuan, $pesan) {
    global $conn;
    $q = mysqli_query($conn, "SELECT wa_token, wa_phone_number_id FROM profil_sekolah LIMIT 1");
    $profil = mysqli_fetch_assoc($q);

    if (!$profil || empty($profil['wa_token']) || empty($profil['wa_phone_number_id'])) {
        return ["status" => false, "message" => "Kredensial belum lengkap di database."];
    }

    $tujuan = preg_replace('/[^0-9]/', '', $tujuan);
    if (substr($tujuan, 0, 1) === '0') {
        $tujuan = '62' . substr($tujuan, 1);
    }

    $url = "https://graph.facebook.com/v18.0/{$profil['wa_phone_number_id']}/messages";
    $payload = json_encode([
        "messaging_product" => "whatsapp",
        "to" => $tujuan,
        "type" => "text",
        "text" => ["body" => $pesan]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $profil['wa_token'],
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [
            "status" => false,
            "code" => 0,
            "stage" => "koneksi",
            "response" => ["curl_error" => $curl_error]
        ];
    }

    $res_arr = json_decode($response, true);

    if ($http_code == 200 && isset($res_arr['messages'][0]['id'])) {
        return [
            "status" => true,
            "http_code" => 200,
            "stage" => "diterima_meta",
            "message_id" => $res_arr['messages'][0]['id'],
            "wa_id" => $res_arr['contacts'][0]['wa_id'] ?? $tujuan,
            "response" => $res_arr
        ];
    }

    return [
        "status" => false,
        "http_code" => $http_code,
        "stage" => "ditolak_meta",
        "response" => $res_arr
    ];
}

function renderStatusBadge($label, $value, $color) {
    return "<span style='display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;background:{$color}15;color:{$color};border:1px solid {$color}30;'>{$label}: {$value}</span>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['action_unlock'])) {
        $input_pass = (string)($_POST['passphrase'] ?? '');
        $input_hash = hash('sha256', $input_pass);

        if ($input_pass !== '' && hash_equals(PASSPHRASE_HASH_ABSENSI, $input_hash)) {
            $_SESSION['wa_passphrase_ok'] = true;
            $_SESSION['wa_notif'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Verifikasi berhasil. Akses edit terbuka.</div>";
        } else {
            $_SESSION['wa_notif'] = "<div class='alert alert-danger'><i class='fas fa-times-circle'></i> Verifikasi gagal. Silakan coba lagi.</div>";
        }
        header("Location: wa_token.php");
        exit;
    }

    if (isset($_POST['action_lock'])) {
        unset($_SESSION['wa_passphrase_ok']);
        $_SESSION['wa_notif'] = "<div class='alert alert-success'><i class='fas fa-lock'></i> Akses edit berhasil dikunci kembali.</div>";
        header("Location: wa_token.php");
        exit;
    }

    if (!$can_edit) {
        $_SESSION['wa_notif'] = "<div class='alert alert-danger'><i class='fas fa-times-circle'></i> Akses ditolak. Lakukan verifikasi terlebih dahulu.</div>";
        header("Location: wa_token.php");
        exit;
    }

    if (isset($_POST['action_save'])) {
        $wa_token = mysqli_real_escape_string($conn, $_POST['wa_token']);
        $wa_phone_id = mysqli_real_escape_string($conn, $_POST['wa_phone_id']);
        $wa_baba = mysqli_real_escape_string($conn, $_POST['wa_baba']);

        $wa_phone_display = preg_replace('/[^0-9]/', '', $_POST['wa_phone_display'] ?? '');
        if (substr($wa_phone_display, 0, 1) === '0') {
            $wa_phone_display = '62' . substr($wa_phone_display, 1);
        }
        $wa_phone_display = mysqli_real_escape_string($conn, $wa_phone_display);

        $update = mysqli_query($conn, "UPDATE profil_sekolah SET wa_token='$wa_token', wa_phone_number_id='$wa_phone_id', wa_business_account_id='$wa_baba', wa_phone_display='$wa_phone_display' WHERE id=1");
        if ($update) {
            $_SESSION['wa_notif'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Konfigurasi WhatsApp Cloud API berhasil disimpan.</div>";
        } else {
            $_SESSION['wa_notif'] = "<div class='alert alert-danger'><i class='fas fa-times-circle'></i> Gagal menyimpan: " . mysqli_error($conn) . "</div>";
        }
        header("Location: wa_token.php");
        exit;
    }

    if (isset($_POST['action_test'])) {
        $no_tujuan = trim($_POST['test_number']);

        if (empty($no_tujuan)) {
            $_SESSION['wa_notif'] = "<div class='alert alert-danger'><i class='fas fa-times-circle'></i> Nomor WhatsApp tujuan tes wajib diisi.</div>";
        } else {
            $pesan_uji = "Tes Koneksi WABA Cloud API Berhasil!";
            $hasil = kirimPesanWhatsApp($no_tujuan, $pesan_uji);

            if ($hasil['status']) {
                $msg_id = htmlspecialchars($hasil['message_id']);
                $wa_id = htmlspecialchars($hasil['wa_id']);

                $_SESSION['wa_notif'] = "
                <div class='alert alert-warning' style='flex-direction:column;align-items:stretch;gap:12px;'>
                    <div style='display:flex;align-items:flex-start;gap:10px;'>
                        <i class='fas fa-circle-check' style='color:#b45309;font-size:16px;margin-top:2px;'></i>
                        <div style='flex:1;'>
                            <div style='font-weight:600;color:#b45309;margin-bottom:4px;'>Pesan Diterima oleh Server Meta</div>
                            <div style='font-size:12.5px;color:#78350f;line-height:1.6;'>
                                Permintaan pengiriman berhasil diproses. Server Meta menerima pesan dan memberikan Message ID.
                            </div>
                        </div>
                    </div>
                    <div style='display:flex;flex-wrap:wrap;gap:8px;padding-top:4px;'>
                        " . renderStatusBadge("HTTP", "200 OK", "#10b981") . "
                        " . renderStatusBadge("Status", "Accepted", "#f59e0b") . "
                        " . renderStatusBadge("Tujuan", $wa_id, "#3b82f6") . "
                    </div>
                    <div style='padding:10px 12px;background:rgba(255,255,255,.6);border:1px solid rgba(245,158,11,.25);border-radius:8px;font-size:11.5px;color:#78350f;font-family:monospace;word-break:break-all;'>
                        Message ID: <strong>{$msg_id}</strong>
                    </div>
                    <div style='padding:12px 14px;background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.22);border-left:3px solid #3b82f6;border-radius:8px;font-size:12.5px;color:#1e40af;line-height:1.65;'>
                        <div style='font-weight:600;margin-bottom:6px;'><i class='fas fa-info-circle'></i> Catatan Penting — Kebijakan Meta</div>
                        <ul style='margin:0;padding-left:18px;line-height:1.7;'>
                            <li><strong>HTTP 200 = pesan diterima Meta</strong>, bukan berarti sudah tampil di HP penerima.</li>
                            <li>Jika nomor penerima <strong>belum pernah membuka obrolan</strong> dengan WhatsApp Business Anda, pesan akan berstatus <em>sent</em> tapi <strong>tidak muncul notifikasi</strong> sampai penerima membuka chat.</li>
                            <li>Kebijakan ini berlaku untuk <strong>mode testing / nomor belum terverifikasi</strong>.</li>
                            <li>Untuk produksi, gunakan <strong>template pesan (HSM)</strong> yang disetujui Meta agar bisa dikirim ke nomor yang belum pernah berinteraksi.</li>
                        </ul>
                    </div>
                    <div style='font-size:11.5px;color:#78350f;opacity:.8;'>
                        Verifikasi lebih lanjut: buka obrolan WhatsApp penerima secara manual, lalu cek riwayat pesan.
                    </div>
                </div>";
            } else {
                $err_msg = $hasil['response']['error']['message'] ?? json_encode($hasil['response']);
                $err_code = $hasil['response']['error']['code'] ?? '-';
                $err_sub = $hasil['response']['error']['error_subcode'] ?? '-';
                $http_code = $hasil['http_code'] ?? '-';
                $stage = $hasil['stage'] ?? 'unknown';
                $stage_label = $stage === 'koneksi' ? 'Koneksi cURL Gagal' : 'Ditolak oleh Server Meta';

                $_SESSION['wa_notif'] = "
                <div class='alert alert-danger' style='flex-direction:column;align-items:stretch;gap:12px;'>
                    <div style='display:flex;align-items:flex-start;gap:10px;'>
                        <i class='fas fa-times-circle' style='color:#b91c1c;font-size:16px;margin-top:2px;'></i>
                        <div style='flex:1;'>
                            <div style='font-weight:600;color:#b91c1c;margin-bottom:4px;'>Pesan Gagal Dikirim</div>
                            <div style='font-size:12.5px;color:#7f1d1d;line-height:1.6;'>
                                Tahap: <strong>{$stage_label}</strong>
                            </div>
                        </div>
                    </div>
                    <div style='display:flex;flex-wrap:wrap;gap:8px;padding-top:4px;'>
                        " . renderStatusBadge("HTTP", (string)$http_code, "#ef4444") . "
                        " . renderStatusBadge("Code", (string)$err_code, "#ef4444") . "
                        " . renderStatusBadge("Subcode", (string)$err_sub, "#ef4444") . "
                    </div>
                    <div style='padding:12px 14px;background:rgba(255,255,255,.65);border:1px solid rgba(239,68,68,.28);border-radius:8px;font-size:12.5px;color:#7f1d1d;line-height:1.65;'>
                        <div style='font-weight:600;margin-bottom:6px;'>Pesan Error dari Meta</div>
                        " . htmlspecialchars($err_msg) . "
                    </div>
                    <details style='font-size:11.5px;color:#7f1d1d;'>
                        <summary style='cursor:pointer;font-weight:600;padding:4px 0;'>Lihat detail teknis (untuk debugging)</summary>
                        <pre style='margin:8px 0 0;padding:10px;background:rgba(255,255,255,.7);border:1px solid rgba(239,68,68,.2);border-radius:6px;font-size:11px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;color:#7f1d1d;'>" . htmlspecialchars(json_encode($hasil['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>
                    </details>
                </div>";
            }
        }
        header("Location: wa_token.php");
        exit;
    }
}

$query = mysqli_query($conn, "SELECT wa_token, wa_phone_number_id, wa_business_account_id, wa_phone_display FROM profil_sekolah LIMIT 1");
$data = mysqli_fetch_assoc($query);

$has_kredensial = !empty($data['wa_token']) && !empty($data['wa_phone_number_id']);
$has_qr = $has_kredensial && !empty($data['wa_phone_display']);

$wa_phone_display_clean = preg_replace('/[^0-9]/', '', $data['wa_phone_display'] ?? '');
if (substr($wa_phone_display_clean, 0, 1) === '0') {
    $wa_phone_display_clean = '62' . substr($wa_phone_display_clean, 1);
}

$wa_qr_link = $has_qr
    ? "https://api.whatsapp.com/send?phone=" . $wa_phone_display_clean . "&text=Aktivasi+Notifikasi+Absensi"
    : "";

if (!$is_direct) {
    return;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan WhatsApp Cloud API</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root{
            --bg:#f6f8fb;
            --surface:#ffffff;
            --surface-2:#f9fafb;
            --line:#e8edf3;
            --line-strong:#dbe3ec;
            --ink:#0f172a;
            --ink-2:#334155;
            --muted:#64748b;
            --muted-2:#94a3b8;
            --brand:#2a5298;
            --brand-2:#1e3c72;
            --brand-soft:rgba(42,82,152,.08);
            --accent:#ffb020;
            --success:#10b981;
            --warning:#f59e0b;
            --danger:#ef4444;
            --info:#3b82f6;
            --whatsapp:#25d366;
            --radius-lg:16px;
            --radius-md:12px;
            --radius-sm:10px;
            --shadow-sm:0 1px 2px rgba(15,23,42,.05);
            --shadow-md:0 6px 16px -8px rgba(15,23,42,.12), 0 2px 6px -2px rgba(15,23,42,.06);
            --shadow-lg:0 18px 40px -18px rgba(15,23,42,.22), 0 8px 18px -10px rgba(15,23,42,.12);
            --shadow-brand:0 14px 30px -14px rgba(30,60,114,.55);
        }

        *{box-sizing:border-box;}

        body{
            font-family:'Rubik', system-ui, -apple-system, "Segoe UI", Tahoma, Geneva, Verdana, sans-serif !important;
            background:
                radial-gradient(circle at 0% 0%, rgba(42,82,152,.06), transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(37,211,102,.05), transparent 40%),
                var(--bg);
            min-height:100vh;
            color:var(--ink);
            margin:0;
            padding:0;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }

        .topbar{
            background:rgba(255,255,255,.88);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            border-bottom:1px solid var(--line);
            position:sticky;
            top:0;
            z-index:100;
            padding:14px 0;
        }

        .topbar-inner{
            max-width:780px;
            margin:0 auto;
            padding:0 24px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
        }

        .brand-block{display:flex;align-items:center;gap:14px;min-width:0;}

        .brand-mark{
            width:44px;
            height:44px;
            border-radius:var(--radius-md);
            background:linear-gradient(135deg, var(--whatsapp), #128c7e);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:20px;
            box-shadow:0 14px 30px -14px rgba(18,140,126,.65);
            flex-shrink:0;
        }

        .brand-titles h1{
            font-size:17px;
            font-weight:600;
            letter-spacing:-.2px;
            color:var(--ink);
            margin:0;
            line-height:1.25;
        }

        .brand-titles span{display:block;font-size:12.5px;color:var(--muted);margin-top:2px;}

        .btn-back{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 16px;
            font-size:13.5px;
            font-weight:500;
            color:var(--ink-2);
            background:var(--surface);
            border:1px solid var(--line);
            border-radius:var(--radius-sm);
            text-decoration:none;
            transition:border-color .2s ease, background .2s ease, transform .2s ease;
            box-shadow:var(--shadow-sm);
        }

        .btn-back:hover{background:var(--surface-2);border-color:var(--line-strong);color:var(--ink);transform:translateX(-2px);}

        .page-wrap{max-width:780px;margin:0 auto;padding:32px 24px 60px;}

        .page-head{margin-bottom:22px;}

        .page-eyebrow{
            font-size:11.5px;
            font-weight:600;
            letter-spacing:1.2px;
            text-transform:uppercase;
            color:var(--whatsapp);
            display:block;
            margin-bottom:6px;
        }

        .page-head h2{font-size:24px;font-weight:600;letter-spacing:-.4px;color:var(--ink);margin:0;}
        .page-head p{font-size:13.5px;color:var(--muted);margin:6px 0 0;}

        .info-banner{
            display:flex;
            align-items:flex-start;
            gap:12px;
            padding:14px 18px;
            border-radius:var(--radius-md);
            background:rgba(37,211,102,.07);
            border:1px solid rgba(37,211,102,.2);
            border-left:4px solid var(--whatsapp);
            color:#047857;
            font-size:13px;
            line-height:1.65;
            margin-bottom:20px;
        }

        .info-banner i{margin-top:2px;font-size:15px;flex-shrink:0;color:var(--whatsapp);}
        .info-banner strong{font-weight:600;color:#065f46;}

        .card-panel{
            background:var(--surface);
            border:1px solid var(--line);
            border-radius:var(--radius-lg);
            box-shadow:var(--shadow-sm);
            margin-bottom:20px;
            overflow:hidden;
            transition:box-shadow .25s ease;
        }

        .card-panel:hover{box-shadow:var(--shadow-md);}

        .card-panel-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:18px 24px;
            border-bottom:1px solid var(--line);
            background:var(--surface-2);
            flex-wrap:wrap;
        }

        .card-panel-title{display:flex;align-items:center;gap:10px;font-size:14.5px;font-weight:600;color:var(--ink);}

        .card-panel-title i{
            width:32px;
            height:32px;
            border-radius:var(--radius-sm);
            background:var(--brand-soft);
            color:var(--brand);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
        }

        .card-panel-title.whatsapp i{background:rgba(37,211,102,.12);color:var(--whatsapp);}
        .card-panel-title.qr i{background:rgba(37,211,102,.12);color:var(--whatsapp);}
        .card-panel-title.lock i{background:rgba(42,82,152,.1);color:#2a5298;}

        .badge-auto{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            font-size:11px;
            font-weight:600;
            letter-spacing:.3px;
            color:#047857;
            background:rgba(37,211,102,.12);
            border:1px solid rgba(37,211,102,.3);
            border-radius:999px;
            text-transform:uppercase;
        }

        .badge-auto i{font-size:10px;}

        .badge-lock{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            font-size:11px;
            font-weight:600;
            letter-spacing:.3px;
            color:#b91c1c;
            background:rgba(239,68,68,.1);
            border:1px solid rgba(239,68,68,.28);
            border-radius:999px;
            text-transform:uppercase;
        }

        .badge-lock i{font-size:10px;}

        .badge-dev{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            font-size:11px;
            font-weight:600;
            letter-spacing:.3px;
            color:#2a5298;
            background:rgba(42,82,152,.12);
            border:1px solid rgba(42,82,152,.3);
            border-radius:999px;
            text-transform:uppercase;
        }

        .badge-dev i{font-size:10px;}

        .card-panel-body{padding:24px;}

        .form-group{margin-bottom:18px;}
        .form-group:last-child{margin-bottom:0;}

        .form-label{display:block;font-size:12.5px;font-weight:500;color:var(--ink-2);margin-bottom:7px;}

        .form-control{
            font-family:inherit;
            font-size:14px;
            color:var(--ink);
            background:var(--surface-2);
            border:1px solid var(--line);
            border-radius:var(--radius-sm);
            padding:10px 14px;
            transition:border-color .2s ease, box-shadow .2s ease, background .2s ease;
            box-shadow:none;
            width:100%;
            outline:none;
        }

        .form-control::placeholder{color:var(--muted-2);}
        .form-control:hover{border-color:var(--line-strong);}
        .form-control:focus{background:#fff;border-color:var(--brand);box-shadow:0 0 0 4px var(--brand-soft);}

        .form-control.is-password{font-family:'Rubik', monospace;letter-spacing:.3px;}

        .form-control:disabled,
        .form-control[disabled]{
            background:#f1f5f9;
            color:var(--muted);
            cursor:not-allowed;
            border-color:var(--line);
            opacity:.85;
        }

        .form-hint{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.55;}

        .input-group{display:flex;gap:8px;}
        .input-group .form-control{flex:1;}

        .input-group .btn-toggle{
            flex-shrink:0;
            padding:10px 16px;
            font-family:inherit;
            font-size:13px;
            font-weight:500;
            color:var(--ink-2);
            background:var(--surface);
            border:1px solid var(--line);
            border-radius:var(--radius-sm);
            cursor:pointer;
            transition:border-color .2s ease, background .2s ease, color .2s ease;
            display:inline-flex;
            align-items:center;
            gap:6px;
            box-shadow:var(--shadow-sm);
        }

        .input-group .btn-toggle:hover{background:var(--surface-2);border-color:var(--line-strong);color:var(--ink);}
        .input-group .btn-toggle:focus-visible{outline:3px solid var(--brand-soft);outline-offset:2px;}

        .alert{
            padding:16px 18px;
            border-radius:var(--radius-md);
            font-size:13.5px;
            font-weight:500;
            border:1px solid transparent;
            margin-bottom:20px;
            display:flex;
            align-items:flex-start;
            gap:10px;
            line-height:1.55;
        }

        .alert-success{background:rgba(16,185,129,.08);color:#047857;border-color:rgba(16,185,129,.28);}
        .alert-warning{background:rgba(245,158,11,.08);color:#b45309;border-color:rgba(245,158,11,.3);}
        .alert-danger{background:rgba(239,68,68,.08);color:#b91c1c;border-color:rgba(239,68,68,.28);}

        .btn{
            font-family:inherit;
            font-size:13.5px;
            font-weight:500;
            border-radius:var(--radius-sm);
            padding:11px 18px;
            transition:transform .2s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease, color .2s ease, filter .2s ease;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            border:1px solid transparent;
            cursor:pointer;
            line-height:1.2;
            text-decoration:none;
        }

        .btn:active{transform:translateY(1px);}
        .btn:focus-visible{outline:3px solid var(--brand-soft);outline-offset:2px;}

        .btn-primary{background:linear-gradient(135deg, var(--brand), var(--brand-2));color:#fff;box-shadow:var(--shadow-brand);}
        .btn-primary:hover{filter:brightness(1.08);transform:translateY(-1px);color:#fff;box-shadow:0 18px 34px -16px rgba(30,60,114,.9);}

        .btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 12px 24px -12px rgba(5,150,105,.7);}
        .btn-success:hover{filter:brightness(1.08);transform:translateY(-1px);color:#fff;}

        .btn-warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;box-shadow:0 12px 24px -12px rgba(217,119,6,.7);}
        .btn-warning:hover{filter:brightness(1.08);transform:translateY(-1px);color:#fff;}

        .btn-ghost{
            background:var(--surface);
            color:var(--ink-2);
            border:1px solid var(--line-strong);
            box-shadow:var(--shadow-sm);
        }
        .btn-ghost:hover{
            background:var(--surface-2);
            color:var(--ink);
            border-color:var(--brand);
        }

        .action-row{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:24px;
            padding-top:22px;
            border-top:1px solid var(--line);
        }

        .action-row .btn{flex:1 1 auto;min-width:180px;}

        .locked-note{
            display:flex;
            align-items:center;
            gap:10px;
            width:100%;
            padding:10px 14px;
            background:rgba(100,116,139,.06);
            border:1px dashed rgba(100,116,139,.3);
            border-radius:var(--radius-sm);
            font-size:12.5px;
            color:#475569;
            line-height:1.55;
        }

        .locked-note i{color:#64748b;font-size:13px;flex-shrink:0;}

        .unlock-box{
            background:rgba(42,82,152,.04);
            border:1px dashed rgba(42,82,152,.2);
            border-radius:var(--radius-md);
            padding:20px;
            margin-bottom:6px;
        }

        .unlock-box-header{
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:14px;
            font-size:13.5px;
            font-weight:600;
            color:#2a5298;
        }

        .unlock-box-header i{
            width:28px;
            height:28px;
            border-radius:8px;
            background:rgba(42,82,152,.12);
            color:#2a5298;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:12px;
        }

        .unlock-hint{font-size:12.5px;color:var(--muted);margin-top:8px;line-height:1.6;}

        .test-section{
            background:var(--surface-2);
            border:1px dashed var(--line-strong);
            border-radius:var(--radius-md);
            padding:20px;
            margin-top:20px;
        }

        .test-section-header{
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:14px;
            font-size:13.5px;
            font-weight:600;
            color:var(--ink);
        }

        .test-section-header i{
            width:28px;
            height:28px;
            border-radius:8px;
            background:rgba(16,185,129,.12);
            color:var(--success);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:12px;
        }

        .test-section-hint{font-size:12.5px;color:var(--muted);margin-top:8px;line-height:1.6;}

        .status-row{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-bottom:16px;
        }

        .status-pill{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:5px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:500;
        }

        .status-pill.ok{background:rgba(16,185,129,.1);color:#047857;border:1px solid rgba(16,185,129,.24);}
        .status-pill.no{background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.24);}
        .status-pill.lock{background:rgba(100,116,139,.1);color:#475569;border:1px solid rgba(100,116,139,.24);}
        .status-pill.dev{background:rgba(42,82,152,.1);color:#2a5298;border:1px solid rgba(42,82,152,.24);}

        .status-pill i{font-size:11px;}

        .qr-layout{
            display:grid;
            grid-template-columns:auto 1fr;
            gap:28px;
            align-items:flex-start;
        }

        .qr-box{
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:14px;
            padding:20px;
            background:#fff;
            border:1px solid var(--line);
            border-radius:var(--radius-md);
            box-shadow:var(--shadow-sm);
            flex-shrink:0;
        }

        #qrcode-container{
            display:flex;
            align-items:center;
            justify-content:center;
            padding:12px;
            background:#fff;
            border-radius:var(--radius-sm);
            min-width:220px;
            min-height:220px;
            line-height:0;
        }

        #qrcode-container canvas{
            display:block !important;
            max-width:100%;
            height:auto;
            border-radius:6px;
        }

        #qrcode-container img{display:none !important;}

        .qr-caption{
            font-size:11.5px;
            color:var(--muted);
            text-align:center;
            max-width:220px;
            line-height:1.5;
        }

        .qr-details{display:flex;flex-direction:column;gap:14px;min-width:0;}

        .qr-details h3{
            font-size:15.5px;
            font-weight:600;
            color:var(--ink);
            letter-spacing:-.15px;
            margin:0;
            line-height:1.35;
        }

        .qr-details p{
            font-size:13px;
            color:var(--muted);
            line-height:1.65;
            margin:0;
        }

        .qr-details p em{font-style:normal;font-weight:600;color:var(--ink-2);background:rgba(37,211,102,.1);padding:2px 6px;border-radius:4px;}

        .qr-link-box{
            display:flex;
            flex-direction:column;
            gap:6px;
            padding:12px 14px;
            background:var(--surface-2);
            border:1px solid var(--line);
            border-radius:var(--radius-sm);
        }

        .qr-link-label{
            font-size:10.5px;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:1px;
            color:var(--muted);
        }

        .qr-link-box code{
            font-family:'Rubik', monospace;
            font-size:12px;
            color:var(--ink-2);
            word-break:break-all;
            line-height:1.5;
        }

        .qr-action-row{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }

        .qr-action-row .btn{flex:1 1 auto;min-width:130px;}

        @media (max-width:900px){
            .topbar-inner{padding:0 18px;}
            .page-wrap{padding:24px 18px 48px;}
            .page-head h2{font-size:21px;}
            .card-panel-body{padding:20px;}
            .card-panel-header{padding:16px 20px;}
        }

        @media (max-width:640px){
            .topbar{padding:12px 0;}
            .topbar-inner{padding:0 14px;}
            .brand-mark{width:38px;height:38px;font-size:17px;}
            .brand-titles h1{font-size:15px;}
            .brand-titles span{font-size:11.5px;}
            .btn-back{padding:8px 12px;font-size:12.5px;}
            .btn-back span{display:none;}
            .page-wrap{padding:20px 14px 40px;}
            .page-head h2{font-size:19px;}
            .page-head p{font-size:12.5px;}
            .card-panel{border-radius:var(--radius-md);}
            .card-panel-body{padding:16px;}
            .card-panel-header{padding:14px 16px;}
            .card-panel-title{font-size:13.5px;}
            .input-group{flex-direction:column;}
            .input-group .btn-toggle{width:100%;justify-content:center;}
            .action-row{flex-direction:column;}
            .action-row .btn{width:100%;min-width:0;}
            .test-section{padding:16px;}
            .unlock-box{padding:16px;}
            .qr-layout{grid-template-columns:1fr;gap:18px;}
            .qr-box{width:100%;padding:16px;}
            #qrcode-container{min-width:0;min-height:0;width:100%;}
            .qr-action-row{flex-direction:column;}
            .qr-action-row .btn{width:100%;}
        }

        @media (prefers-reduced-motion:reduce){
            *{animation:none !important;transition:none !important;}
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand-block">
                <div class="brand-mark"><i class="fab fa-whatsapp"></i></div>
                <div class="brand-titles">
                    <h1>Pengaturan WhatsApp API</h1>
                    <span>Konfigurasi WhatsApp Cloud API</span>
                </div>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    <div class="page-wrap">

        <div class="page-head">
            <span class="page-eyebrow">Integrasi</span>
            <h2>WhatsApp Cloud API</h2>
            <p>Atur kredensial WABA dan bagikan QR aktivasi ke orang tua / wali siswa.</p>
        </div>

        <div class="info-banner">
            <i class="fas fa-circle-info"></i>
            <div>
                <strong>Info:</strong> Isi kredensial WABA dari Meta Developers. Nomor WhatsApp Bisnis yang Anda masukkan akan otomatis di-encode menjadi QR aktivasi untuk orang tua / wali siswa.
            </div>
        </div>

        <?php if (!$can_edit): ?>
        <div class="info-banner" style="background:rgba(42,82,152,.05);border-color:rgba(42,82,152,.18);border-left-color:#2a5298;color:#334155;">
            <i class="fas fa-circle-info" style="color:#2a5298;"></i>
            <div>
                Beberapa pengaturan pada halaman ini memerlukan verifikasi tambahan sebelum dapat diubah.
            </div>
        </div>
        <?php elseif ($passphrase_unlocked && !$is_developer): ?>
        <div class="info-banner" style="background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.28);border-left-color:#10b981;color:#047857;">
            <i class="fas fa-unlock" style="color:#10b981;"></i>
            <div>
                <strong>Akses Terbuka:</strong> Anda dapat mengubah konfigurasi. Jangan lupa kunci kembali setelah selesai.
            </div>
        </div>
        <?php endif; ?>

        <?= $pesan_notif; ?>

        <form method="POST" action="">
            <div class="card-panel">
                <div class="card-panel-header">
                    <div class="card-panel-title whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        Kredensial WABA
                    </div>
                    <?php if ($is_developer): ?>
                        <span class="badge-dev"><i class="fas fa-user-shield"></i> Developer</span>
                    <?php elseif ($passphrase_unlocked): ?>
                        <span class="badge-auto"><i class="fas fa-unlock"></i> Unlocked</span>
                    <?php else: ?>
                        <span class="badge-lock"><i class="fas fa-lock"></i> Locked</span>
                    <?php endif; ?>
                </div>
                <div class="card-panel-body">

                    <div class="status-row">
                        <?php if ($has_kredensial): ?>
                            <span class="status-pill ok"><i class="fas fa-circle-check"></i> Kredensial Terkonfigurasi</span>
                        <?php else: ?>
                            <span class="status-pill no"><i class="fas fa-circle-xmark"></i> Kredensial Belum Diisi</span>
                        <?php endif; ?>

                        <?php if ($is_developer): ?>
                            <span class="status-pill dev"><i class="fas fa-user-shield"></i> Akses Developer</span>
                        <?php elseif ($passphrase_unlocked): ?>
                            <span class="status-pill ok"><i class="fas fa-unlock"></i> Terverifikasi</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="wa_phone_id">Phone Number ID</label>
                        <input type="text" id="wa_phone_id" name="wa_phone_id" class="form-control"
                               value="<?= htmlspecialchars($data['wa_phone_number_id'] ?? ''); ?>"
                               placeholder="Contoh: 123456789012345"
                               <?= $can_edit ? '' : 'disabled'; ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="wa_baba">WhatsApp Business Account ID (WABA ID)</label>
                        <input type="text" id="wa_baba" name="wa_baba" class="form-control"
                               value="<?= htmlspecialchars($data['wa_business_account_id'] ?? ''); ?>"
                               placeholder="Contoh: 098765432109876"
                               <?= $can_edit ? '' : 'disabled'; ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="wa_phone_display">Nomor WhatsApp Bisnis (yang ditampilkan ke orang tua)</label>
                        <input type="text" id="wa_phone_display" name="wa_phone_display" class="form-control"
                               value="<?= htmlspecialchars($data['wa_phone_display'] ?? ''); ?>"
                               placeholder="Contoh: 6289666164080"
                               <?= $can_edit ? '' : 'disabled'; ?>>
                        <div class="form-hint">
                            Nomor ini yang akan di-encode ke dalam QR aktivasi. Format <strong>08xx</strong> otomatis dikonversi ke <strong>628xx</strong>.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="wa_token">Permanent Access Token (Meta)</label>
                        <div class="input-group">
                            <input type="password" id="wa_token" name="wa_token" class="form-control is-password"
                                   value="<?= htmlspecialchars($data['wa_token'] ?? ''); ?>"
                                   placeholder="Tempel token akses di sini..."
                                   <?= $can_edit ? '' : 'disabled'; ?>>
                            <?php if ($can_edit): ?>
                                <button type="button" id="toggleToken" class="btn-toggle" onclick="toggleTokenVisibility()">
                                    <i class="fas fa-eye" id="toggleIcon"></i> <span id="toggleText">Lihat</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($can_edit): ?>
                        <div class="test-section">
                            <div class="test-section-header">
                                <i class="fas fa-vial"></i>
                                Uji Koneksi WABA
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="test_number">Nomor WhatsApp Tujuan (Untuk Tes Koneksi)</label>
                                <input type="text" id="test_number" name="test_number" class="form-control" placeholder="Contoh: 6281234567890">
                                <div class="test-section-hint">
                                    Masukkan nomor WhatsApp aktif untuk menerima pesan uji coba. Bisa menggunakan format <strong>08xx</strong> atau <strong>628xx</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="action-row">
                            <button type="submit" name="action_save" class="btn btn-primary">
                                <i class="fas fa-floppy-disk"></i> Simpan Perubahan
                            </button>
                            <button type="submit" name="action_test" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Tes Koneksi WABA
                            </button>
                            <?php if ($passphrase_unlocked && !$is_developer): ?>
                                <button type="submit" name="action_lock" class="btn btn-warning" formnovalidate>
                                    <i class="fas fa-lock"></i> Kunci Kembali
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="action-row" style="border-top:none;padding-top:0;margin-top:20px;">
                            <div class="locked-note">
                                <i class="fas fa-circle-info"></i>
                                <span>Halaman ini menampilkan data dalam mode pratinjau. Untuk melakukan perubahan, ikuti langkah pada bagian bawah halaman.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </form>

        <?php if (!$can_edit): ?>
        <form method="POST" action="">
            <div class="card-panel">
                <div class="card-panel-header">
                    <div class="card-panel-title lock">
                        <i class="fas fa-shield-halved"></i>
                        Verifikasi Tambahan
                    </div>
                </div>
                <div class="card-panel-body">
                    <div class="unlock-box">
                        <div class="unlock-box-header">
                            <i class="fas fa-key"></i>
                            Konfirmasi Akses
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="passphrase">Kode Verifikasi</label>
                            <div class="input-group">
                                <input type="password" id="passphrase" name="passphrase" class="form-control is-password"
                                       placeholder="Masukkan kode..." autocomplete="off" required>
                                <button type="button" id="togglePass" class="btn-toggle" onclick="togglePassVisibility()">
                                    <i class="fas fa-eye" id="togglePassIcon"></i> <span id="togglePassText">Lihat</span>
                                </button>
                            </div>
                            <div class="unlock-hint">
                                Konfirmasi identitas Anda untuk melanjutkan perubahan.
                            </div>
                        </div>
                    </div>
                    <div class="action-row">
                        <button type="submit" name="action_unlock" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i> Verifikasi
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($has_qr): ?>
        <div class="card-panel">
            <div class="card-panel-header">
                <div class="card-panel-title qr">
                    <i class="fas fa-qrcode"></i>
                    QR Aktivasi Orang Tua / Wali
                </div>
                <span class="badge-auto"><i class="fas fa-bolt"></i> Auto Generated</span>
            </div>
            <div class="card-panel-body">
                <div class="qr-layout">
                    <div class="qr-box">
                        <div id="qrcode-container"></div>
                        <div class="qr-caption">Scan untuk terhubung ke WhatsApp resmi sekolah</div>
                    </div>
                    <div class="qr-details">
                        <h3>Bagikan QR ini ke Orang Tua / Wali</h3>
                        <p>
                            Orang tua cukup scan QR ini untuk membuka chat WhatsApp resmi sekolah dengan pesan otomatis
                            <em>Aktivasi Notifikasi Absensi</em>. Cocok ditempel di papan pengumuman, grup kelas, atau dibagikan lewat cetakan.
                        </p>
                        <div class="qr-link-box">
                            <span class="qr-link-label">Link WhatsApp</span>
                            <code id="qrLinkText"><?= htmlspecialchars($wa_qr_link); ?></code>
                        </div>
                        <div class="qr-action-row">
                            <button type="button" class="btn btn-primary" onclick="downloadQR()">
                                <i class="fas fa-download"></i> Unduh PNG
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="printQR()">
                                <i class="fas fa-print"></i> Cetak
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="copyQRLink(event)">
                                <i class="fas fa-copy"></i> Salin Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleTokenVisibility() {
            const tokenInput = document.getElementById('wa_token');
            const toggleIcon = document.getElementById('toggleIcon');
            const toggleText = document.getElementById('toggleText');
            if (!tokenInput || !toggleIcon || !toggleText) return;
            if (tokenInput.type === 'password') {
                tokenInput.type = 'text';
                toggleText.textContent = 'Sembunyikan';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                tokenInput.type = 'password';
                toggleText.textContent = 'Lihat';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        function togglePassVisibility() {
            const passInput = document.getElementById('passphrase');
            const toggleIcon = document.getElementById('togglePassIcon');
            const toggleText = document.getElementById('togglePassText');
            if (!passInput || !toggleIcon || !toggleText) return;
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleText.textContent = 'Sembunyikan';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passInput.type = 'password';
                toggleText.textContent = 'Lihat';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        <?php if ($has_qr): ?>
        (function() {
            const container = document.getElementById('qrcode-container');
            const linkText = document.getElementById('qrLinkText');
            if (!container || !linkText) return;
            const link = linkText.textContent.trim();
            if (!link) return;

            try {
                container.innerHTML = '';
                new QRCode(container, {
                    text: link,
                    width: 200,
                    height: 200,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                container.innerHTML = '<div style="font-size:12px;color:#94a3b8;text-align:center;padding:20px;">Gagal membuat QR: ' + e.message + '</div>';
            }
        })();

        function downloadQR() {
            const canvas = document.querySelector('#qrcode-container canvas');
            let dataUrl = null;
            if (canvas) {
                dataUrl = canvas.toDataURL('image/png');
            } else {
                const img = document.querySelector('#qrcode-container img');
                if (img && img.src) dataUrl = img.src;
            }
            if (!dataUrl) { alert('QR belum siap, coba beberapa saat lagi.'); return; }
            const a = document.createElement('a');
            a.href = dataUrl;
            a.download = 'QR-WABA-Aktivasi-Absensi.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function printQR() {
            const canvas = document.querySelector('#qrcode-container canvas');
            let src = null;
            if (canvas) src = canvas.toDataURL('image/png');
            else {
                const img = document.querySelector('#qrcode-container img');
                if (img && img.src) src = img.src;
            }
            if (!src) { alert('QR belum siap, coba beberapa saat lagi.'); return; }
            const link = document.getElementById('qrLinkText').textContent.trim();
            const win = window.open('', '_blank', 'width=520,height=680');
            if (!win) { alert('Popup diblokir. Izinkan popup untuk mencetak QR.'); return; }
            win.document.write(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cetak QR Aktivasi</title>' +
                '<link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">' +
                '<style>' +
                'body{font-family:"Rubik",sans-serif;margin:0;padding:40px;display:flex;flex-direction:column;align-items:center;text-align:center;color:#0f172a;}' +
                'img{width:320px;height:320px;padding:16px;background:#fff;border:1px solid #e8edf3;border-radius:16px;box-shadow:0 10px 30px -12px rgba(15,23,42,.25);}' +
                'h1{font-size:22px;font-weight:600;margin:24px 0 8px;letter-spacing:-.3px;}' +
                'p{font-size:14px;color:#64748b;margin:0;max-width:420px;line-height:1.6;}' +
                '.tag{display:inline-block;margin-top:18px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;color:#047857;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.3);letter-spacing:.5px;}' +
                '.url{margin-top:22px;font-family:monospace;font-size:11px;color:#94a3b8;word-break:break-all;max-width:420px;}' +
                '@media print{body{padding:20px;}button{display:none;}}' +
                '</style></head><body>' +
                '<img src="' + src + '" alt="QR Aktivasi">' +
                '<h1>Aktivasi Notifikasi Absensi</h1>' +
                '<p>Scan QR ini dengan WhatsApp untuk terhubung ke nomor resmi sekolah dan aktifkan notifikasi absensi.</p>' +
                '<span class="tag">WhatsApp Resmi Sekolah</span>' +
                '<div class="url">' + link + '</div>' +
                '</body></html>'
            );
            win.document.close();
            win.focus();
            setTimeout(function() { try { win.print(); } catch(e){} }, 600);
        }

        function copyQRLink(ev) {
            const codeEl = document.getElementById('qrLinkText');
            if (!codeEl) return;
            const text = codeEl.textContent.trim();
            const btn = ev && ev.currentTarget ? ev.currentTarget : null;
            const original = btn ? btn.innerHTML : null;

            function feedback(ok) {
                if (!btn) return;
                btn.innerHTML = ok
                    ? '<i class="fas fa-check"></i> Tersalin!'
                    : '<i class="fas fa-times"></i> Gagal';
                setTimeout(function() { if (btn) btn.innerHTML = original; }, 1800);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function(){ feedback(true); }).catch(function(){
                    fallbackCopy(text) ? feedback(true) : feedback(false);
                });
            } else {
                fallbackCopy(text) ? feedback(true) : feedback(false);
            }
        }

        function fallbackCopy(text) {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                return ok;
            } catch (e) { return false; }
        }
        <?php endif; ?>

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

</body>
</html>