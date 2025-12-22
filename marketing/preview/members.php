<?php
// Demo Preview - Members
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Kampüs - Members Preview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 24px;
            min-height: 100vh;
        }
        .members-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #111827;
        }
        .btn-add {
            background: #6366f1;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #6366f1;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        .btn-add:hover {
            background: #4f46e5;
        }
        .search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .search-input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .search-input:focus {
            outline: none;
            border-color: #6366f1;
        }
        .search-btn {
            background: #6366f1;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #6366f1;
            font-weight: 500;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        .search-btn:hover {
            background: #4f46e5;
        }
        .members-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
        th {
            padding: 20px;
            text-align: left;
            font-weight: 700;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tbody tr {
            transition: background 0.2s;
        }
        tbody tr:hover {
            background: #f9fafb;
        }
        .member-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        .member-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }
        .member-email {
            font-size: 13px;
            color: #6b7280;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            background: #e0e7ff;
            color: #6366f1;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 50%, #000000 100%);
        }
        body.dark-mode .page-title {
            color: #ffffff;
        }
        body.dark-mode .btn-add {
            background: #8b5cf6;
            border-color: #8b5cf6;
        }
        body.dark-mode .btn-add:hover {
            background: #7c3aed;
        }
        body.dark-mode .search-input {
            background: #000000;
            border-color: rgba(99, 102, 241, 0.3);
            color: #ffffff;
        }
        body.dark-mode .search-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        body.dark-mode .search-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        body.dark-mode .search-btn {
            background: #8b5cf6;
            border-color: #8b5cf6;
        }
        body.dark-mode .search-btn:hover {
            background: #7c3aed;
        }
        body.dark-mode .members-table {
            background: #0a0a0a;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.4);
        }
        body.dark-mode thead {
            background: linear-gradient(135deg, #0a0a0a 0%, #111111 100%);
        }
        body.dark-mode th {
            color: #ffffff;
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode td {
            color: #cbd5e1;
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }
        body.dark-mode tbody tr:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        body.dark-mode .member-name {
            color: #ffffff;
        }
        body.dark-mode .member-email {
            color: #94a3b8;
        }
        body.dark-mode .badge {
            background: rgba(99, 102, 241, 0.2);
            color: #8b5cf6;
        }
    </style>
</head>
<body>
    <div class="members-container">
        <div class="page-header">
            <h1 class="page-title">Üyeler</h1>
            <button class="btn-add">
                <i class="fas fa-plus"></i>
                Yeni Üye
            </button>
        </div>
        
        <div class="search-bar">
            <input type="text" class="search-input" placeholder="Üye ara...">
            <button class="search-btn">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="members-table">
            <table>
                <thead>
                    <tr>
                        <th>Üye</th>
                        <th>Email</th>
                        <th>Öğrenci No</th>
                        <th>Telefon</th>
                        <th>Kayıt Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="member-info">
                                <div class="avatar">AY</div>
                                <div>
                                    <div class="member-name">Ahmet Yılmaz</div>
                                    <div class="member-email">ahmet.yilmaz@university.edu</div>
                                </div>
                            </div>
                        </td>
                        <td>ahmet.yilmaz@university.edu</td>
                        <td><span class="badge">2021001</span></td>
                        <td>0532 123 45 67</td>
                        <td>15 Eyl 2024</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="member-info">
                                <div class="avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">MÖ</div>
                                <div>
                                    <div class="member-name">Mehmet Özkan</div>
                                    <div class="member-email">mehmet.ozkan@university.edu</div>
                                </div>
                            </div>
                        </td>
                        <td>mehmet.ozkan@university.edu</td>
                        <td><span class="badge">2021002</span></td>
                        <td>0533 234 56 78</td>
                        <td>16 Eyl 2024</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="member-info">
                                <div class="avatar" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">AK</div>
                                <div>
                                    <div class="member-name">Ayşe Kaya</div>
                                    <div class="member-email">ayse.kaya@university.edu</div>
                                </div>
                            </div>
                        </td>
                        <td>ayse.kaya@university.edu</td>
                        <td><span class="badge">2021003</span></td>
                        <td>0534 345 67 89</td>
                        <td>17 Eyl 2024</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
