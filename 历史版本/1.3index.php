<?php
/**
 * 尚显创新积分统计系统 - 单文件精简版
 * 文件: index.php
 */

// 配置信息
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('DATA_FILE', 'innovation_data.txt');
define('USERS_FILE', 'users.txt');
define('DEPARTMENTS', ['售前部', '售后部', '店长运营部', '生产部']);

// 初始化会话
session_start();

// 工具函数
function loadData($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function authenticate($username, $password) {
    return $username === ADMIN_USERNAME && $password === ADMIN_PASSWORD;
}

// 处理登录
if (isset($_POST['login'])) {
    if (authenticate($_POST['username'], $_POST['password'])) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 处理注销
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 加载数据
$data = loadData(DATA_FILE);
$users = loadData(USERS_FILE);

// 获取所有已输入过的姓名
$allNames = array_unique(array_column($data, 'name'));

// 处理数据操作
if (isAdmin()) {
    // 添加记录
    if (isset($_POST['add'])) {
        $newRecord = [
            'id' => uniqid(),
            'date' => $_POST['date'],
            'department' => $_POST['department'],
            'name' => $_POST['name'],
            'content' => $_POST['content'],
            'quantity' => (int)$_POST['quantity'],
            'points' => (int)$_POST['points'],
            'implemented' => isset($_POST['implemented']) ? true : false,
            'is_additional' => isset($_POST['is_additional']) ? true : false,
            'month_for_points' => $_POST['month_for_points'] ?? date('Y-m', strtotime($_POST['date']))
        ];
        
        $data[] = $newRecord;
        saveData(DATA_FILE, $data);
        header('Location: index.php');
        exit;
    }
    
    // 编辑记录
    if (isset($_POST['edit'])) {
        foreach ($data as &$record) {
            if ($record['id'] === $_POST['id']) {
                $record['date'] = $_POST['date'];
                $record['department'] = $_POST['department'];
                $record['name'] = $_POST['name'];
                $record['content'] = $_POST['content'];
                $record['quantity'] = (int)$_POST['quantity'];
                $record['points'] = (int)$_POST['points'];
                $record['implemented'] = isset($_POST['implemented']) ? true : false;
                $record['is_additional'] = isset($_POST['is_additional']) ? true : false;
                $record['month_for_points'] = $_POST['month_for_points'];
                break;
            }
        }
        saveData(DATA_FILE, $data);
        header('Location: index.php');
        exit;
    }
    
    // 删除记录
    if (isset($_GET['delete'])) {
        $data = array_filter($data, function($record) {
            return $record['id'] !== $_GET['delete'];
        });
        saveData(DATA_FILE, $data);
        header('Location: index.php');
        exit;
    }
}

// 统计函数
function getPeriodData($data, $period, $forPoints = false) {
    $today = new DateTime();
    $startDate = null;
    $endDate = $today->format('Y-m-d');
    
    switch ($period) {
        case 'week':
            $startDate = (clone $today)->modify('monday this week')->format('Y-m-d');
            break;
        case 'month':
            $startDate = (clone $today)->modify('first day of this month')->format('Y-m-d');
            break;
        case 'quarter':
            $month = $today->format('n');
            $quarterStartMonth = ((int)($month - 1) / 3) * 3 + 1;
            $startDate = (new DateTime($today->format('Y') . '-' . $quarterStartMonth . '-01'))->format('Y-m-d');
            break;
        default:
            return $data;
    }
    
    return array_filter($data, function($record) use ($startDate, $endDate, $period, $forPoints) {
        if ($forPoints && $record['is_additional']) {
            // 追加积分的记录，使用month_for_points字段
            $recordMonth = date('Y-m', strtotime($record['date']));
            $targetMonth = date('Y-m', strtotime($startDate));
            return $recordMonth >= $targetMonth;
        } else {
            // 普通记录使用日期范围
            return $record['date'] >= $startDate && $record['date'] <= $endDate;
        }
    });
}

function getPersonRanking($data, $period) {
    $periodData = getPeriodData($data, $period, true);
    $ranking = [];
    
    foreach ($periodData as $record) {
        $key = $record['department'] . '-' . $record['name'];
        
        if (!isset($ranking[$key])) {
            $ranking[$key] = [
                'department' => $record['department'],
                'name' => $record['name'],
                'points' => 0,
                'quantity' => 0,
                'implemented' => 0
            ];
        }
        
        $ranking[$key]['points'] += $record['points'];
        if (!$record['is_additional']) {
            $ranking[$key]['quantity'] += $record['quantity'];
        }
        $ranking[$key]['implemented'] += $record['implemented'] ? 1 : 0;
    }
    
    usort($ranking, function($a, $b) {
        return $b['points'] - $a['points'];
    });
    
    return $ranking;
}

function getDepartmentRanking($data, $period) {
    $periodData = getPeriodData($data, $period, true);
    $ranking = [];
    
    foreach ($periodData as $record) {
        $department = $record['department'];
        
        if (!isset($ranking[$department])) {
            $ranking[$department] = [
                'department' => $department,
                'points' => 0,
                'quantity' => 0,
                'implemented' => 0
            ];
        }
        
        $ranking[$department]['points'] += $record['points'];
        if (!$record['is_additional']) {
            $ranking[$department]['quantity'] += $record['quantity'];
        }
        $ranking[$department]['implemented'] += $record['implemented'] ? 1 : 0;
    }
    
    usort($ranking, function($a, $b) {
        return $b['points'] - $a['points'];
    });
    
    return $ranking;
}

function getCompanyRanking($data, $period) {
    $periodData = getPeriodData($data, $period, true);
    $ranking = [
        'points' => 0,
        'quantity' => 0,
        'implemented' => 0
    ];
    
    foreach ($periodData as $record) {
        $ranking['points'] += $record['points'];
        if (!$record['is_additional']) {
            $ranking['quantity'] += $record['quantity'];
        }
        $ranking['implemented'] += $record['implemented'] ? 1 : 0;
    }
    
    return $ranking;
}

// 获取当前视图
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$editId = isset($_GET['edit']) ? $_GET['edit'] : null;

// 获取编辑记录
$editRecord = null;
if ($editId) {
    foreach ($data as $record) {
        if ($record['id'] === $editId) {
            $editRecord = $record;
            break;
        }
    }
}

// 生成月份选择列表
$monthOptions = [];
$currentMonth = date('Y-m');
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthOptions[] = $month;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>尚显创新积分统计系统</title>
    <style>
        body { font-family: 'Microsoft YaHei', sans-serif; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        .header { background-color: #333; color: white; padding: 15px 0; }
        .header h1 { margin: 0; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .nav ul { list-style: none; margin: 0; padding: 0; display: flex; }
        .nav ul li { margin-right: 20px; }
        .nav ul li a { color: white; text-decoration: none; }
        .content { padding: 30px 0; }
        .card { background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .card-header { margin-bottom: 15px; }
        .card-header h2 { margin: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background-color: #007BFF; color: white; }
        .btn-danger { background-color: #DC3545; color: white; }
        .btn-secondary { background-color: #6C757D; color: white; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .tabs { margin-bottom: 20px; }
        .tabs ul { list-style: none; margin: 0; padding: 0; display: flex; }
        .tabs ul li { margin-right: 10px; }
        .tabs ul li a { display: block; padding: 8px 15px; background-color: #f2f2f2; border-radius: 4px 4px 0 0; text-decoration: none; color: #333; }
        .tabs ul li a.active { background-color: #007BFF; color: white; }
        .period-tabs { margin-bottom: 15px; }
        .period-tabs a { margin-right: 10px; }
        .period-tabs a.active { font-weight: bold; color: #007BFF; }
        .implemented-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .implemented-yes { background-color: #d4edda; color: #155724; }
        .implemented-no { background-color: #f8d7da; color: #721c24; }
        .ranking-badge { display: inline-block; width: 20px; height: 20px; line-height: 20px; text-align: center; border-radius: 50%; font-weight: bold; }
        .ranking-1 { background-color: #ffc107; color: #333; }
        .ranking-2 { background-color: #6c757d; color: white; }
        .ranking-3 { background-color: #fd7e14; color: white; }
        .additional-field { display: none; }
        .additional-badge { background-color: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav">
                <h1>尚显创新积分统计系统</h1>
                <?php if (isAdmin()): ?>
                <ul>
                    <li><a href="?logout">退出登录</a></li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <?php if (!isAdmin()): ?>
            <!-- 登录表单 -->
            <div class="card">
                <div class="card-header">
                    <h2>管理员登录</h2>
                </div>
                <form method="post">
                    <input type="hidden" name="login" value="1">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <?php if (isset($loginError)): ?>
                    <div class="alert alert-danger"><?php echo $loginError; ?></div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">登录</button>
                </form>
            </div>
            <?php else: ?>
            <!-- 管理界面 -->
            <div class="tabs">
                <ul>
                    <li><a href="?view=list" class="<?php echo $view == 'list' ? 'active' : ''; ?>">数据列表</a></li>
                    <li><a href="?view=person" class="<?php echo $view == 'person' ? 'active' : ''; ?>">个人排行</a></li>
                    <li><a href="?view=department" class="<?php echo $view == 'department' ? 'active' : ''; ?>">部门排行</a></li>
                    <li><a href="?view=company" class="<?php echo $view == 'company' ? 'active' : ''; ?>">公司统计</a></li>
                </ul>
            </div>

            <?php if ($view == 'list'): ?>
            <!-- 数据列表视图 -->
            <div class="card">
                <div class="card-header">
                    <div class="flex justify-between items-center">
                        <h2>创新积分记录</h2>
                        <button id="showAddFormBtn" class="btn btn-primary">添加记录</button>
                    </div>
                </div>
                
                <!-- 添加记录表单 -->
                <div id="addRecordForm" class="mb-4" style="display: none;">
                    <form method="post">
                        <input type="hidden" name="add" value="1">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="date">日期</label>
                                <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="department">部门</label>
                                <select id="department" name="department" required>
                                    <?php foreach (DEPARTMENTS as $dept): ?>
                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="name">姓名</label>
                                <input type="text" id="name" name="name" required list="namesList">
                                <datalist id="namesList">
                                    <?php foreach ($allNames as $name): ?>
                                    <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="quantity">创新数量</label>
                                <input type="number" id="quantity" name="quantity" required min="1" value="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="content">创新内容</label>
                            <textarea id="content" name="content" required></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="points">创新积分</label>
                                <input type="number" id="points" name="points" required min="1" value="1">
                            </div>
                            <div class="form-group">
                                <label for="implemented">是否落地实施</label>
                                <input type="checkbox" id="implemented" name="implemented" value="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="isAdditional" name="is_additional" value="1">
                                本月或之后追加积分（不计入创新数量）
                            </label>
                        </div>
                        <div id="additionalPointsFields" class="additional-field grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="month_for_points">积分计入月份</label>
                                <select id="month_for_points" name="month_for_points" required>
                                    <?php foreach ($monthOptions as $month): ?>
                                    <option value="<?php echo $month; ?>" <?php echo $month == date('Y-m') ? 'selected' : ''; ?>><?php echo $month; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </form>
                </div>
                
                <!-- 编辑记录表单 -->
                <?php if ($editRecord): ?>
                <div class="mb-4">
                    <h3>编辑记录</h3>
                    <form method="post">
                        <input type="hidden" name="edit" value="1">
                        <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="date">日期</label>
                                <input type="date" id="date" name="date" required value="<?php echo $editRecord['date']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="department">部门</label>
                                <select id="department" name="department" required>
                                    <?php foreach (DEPARTMENTS as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo $editRecord['department'] == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="name">姓名</label>
                                <input type="text" id="name" name="name" required value="<?php echo $editRecord['name']; ?>" list="namesList">
                                <datalist id="namesList">
                                    <?php foreach ($allNames as $name): ?>
                                    <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="quantity">创新数量</label>
                                <input type="number" id="quantity" name="quantity" required min="1" value="<?php echo $editRecord['quantity']; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="content">创新内容</label>
                            <textarea id="content" name="content" required><?php echo $editRecord['content']; ?></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="points">创新积分</label>
                                <input type="number" id="points" name="points" required min="1" value="<?php echo $editRecord['points']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="implemented">是否落地实施</label>
                                <input type="checkbox" id="implemented" name="implemented" value="1" <?php echo $editRecord['implemented'] ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="isAdditionalEdit" name="is_additional" value="1" <?php echo isset($editRecord['is_additional']) && $editRecord['is_additional'] ? 'checked' : ''; ?>>
                                本月或之后追加积分（不计入创新数量）
                            </label>
                        </div>
                        <div id="additionalPointsFieldsEdit" class="additional-field grid grid-cols-2 gap-4" <?php echo isset($editRecord['is_additional']) && $editRecord['is_additional'] ? '' : 'style="display: none;"'; ?>>
                            <div class="form-group">
                                <label for="month_for_points">积分计入月份</label>
                                <select id="month_for_points" name="month_for_points" required>
                                    <?php foreach ($monthOptions as $month): ?>
                                    <option value="<?php echo $month; ?>" <?php echo isset($editRecord['month_for_points']) && $editRecord['month_for_points'] == $month ? 'selected' : ''; ?>><?php echo $month; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button type="submit" class="btn btn-primary">保存</button>
                            <a href="index.php" class="btn btn-secondary">取消</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- 数据表格 -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>部门</th>
                            <th>姓名</th>
                            <th>创新内容</th>
                            <th>创新数量</th>
                            <th>创新积分</th>
                            <th>是否实施</th>
                            <th>类型</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $record): ?>
                        <tr>
                            <td><?php echo $record['date']; ?></td>
                            <td><?php echo $record['department']; ?></td>
                            <td><?php echo $record['name']; ?></td>
                            <td><?php echo mb_strimwidth($record['content'], 0, 50, '...'); ?></td>
                            <td><?php echo $record['quantity']; ?></td>
                            <td><?php echo $record['points']; ?></td>
                            <td>
                                <span class="implemented-badge <?php echo $record['implemented'] ? 'implemented-yes' : 'implemented-no'; ?>">
                                    <?php echo $record['implemented'] ? '是' : '否'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($record['is_additional']) && $record['is_additional']): ?>
                                <span class="additional-badge">追加积分</span>
                                <div class="text-xs text-gray-500">计入: <?php echo $record['month_for_points']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $record['id']; ?>" class="btn btn-primary">编辑</a>
                                <a href="?delete=<?php echo $record['id']; ?>" class="btn btn-danger" onclick="return confirm('确定要删除这条记录吗？')">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($view == 'person' || $view == 'department' || $view == 'company'): ?>
            <!-- 统计视图 -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <?php 
                        if ($view == 'person') echo '个人排行榜';
                        elseif ($view == 'department') echo '部门排行榜';
                        else echo '公司统计';
                        ?>
                    </h2>
                </div>
                
                <div class="period-tabs">
                    <a href="?view=<?php echo $view; ?>&period=week" class="<?php echo $period == 'week' ? 'active' : ''; ?>">周统计</a>
                    <a href="?view=<?php echo $view; ?>&period=month" class="<?php echo $period == 'month' ? 'active' : ''; ?>">月统计</a>
                    <a href="?view=<?php echo $view; ?>&period=quarter" class="<?php echo $period == 'quarter' ? 'active' : ''; ?>">季度统计</a>
                </div>
                
                <?php if ($view == 'person'): ?>
                <!-- 个人排行榜 -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>部门</th>
                            <th>姓名</th>
                            <th>创新数量</th>
                            <th>创新积分</th>
                            <th>落地实施数量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ranking = getPersonRanking($data, $period);
                        foreach ($ranking as $index => $item): 
                        ?>
                        <tr>
                            <td>
                                <span class="ranking-badge <?php echo $index < 3 ? 'ranking-' . ($index + 1) : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </span>
                            </td>
                            <td><?php echo $item['department']; ?></td>
                            <td><?php echo $item['name']; ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo $item['points']; ?></td>
                            <td><?php echo $item['implemented']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif ($view == 'department'): ?>
                <!-- 部门排行榜 -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>部门</th>
                            <th>创新数量</th>
                            <th>创新积分</th>
                            <th>落地实施数量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ranking = getDepartmentRanking($data, $period);
                        foreach ($ranking as $index => $item): 
                        ?>
                        <tr>
                            <td>
                                <span class="ranking-badge <?php echo $index < 3 ? 'ranking-' . ($index + 1) : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </span>
                            </td>
                            <td><?php echo $item['department']; ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo $item['points']; ?></td>
                            <td><?php echo $item['implemented']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif ($view == 'company'): ?>
                <!-- 公司统计 -->
                <div class="bg-light p-4 rounded">
                    <h3>公司<?php echo $period == 'week' ? '周' : ($period == 'month' ? '月' : '季度'); ?>统计</h3>
                    <div class="grid grid-cols-3 gap-4 mt-3">
                        <div class="bg-white p-3 rounded shadow-sm">
                            <div class="text-sm text-gray-500">总创新数量</div>
                            <div class="text-2xl font-bold"><?php echo getCompanyRanking($data, $period)['quantity']; ?></div>
                        </div>
                        <div class="bg-white p-3 rounded shadow-sm">
                            <div class="text-sm text-gray-500">总创新积分</div>
                            <div class="text-2xl font-bold"><?php echo getCompanyRanking($data, $period)['points']; ?></div>
                        </div>
                        <div class="bg-white p-3 rounded shadow-sm">
                            <div class="text-sm text-gray-500">落地实施数量</div>
                            <div class="text-2xl font-bold"><?php echo getCompanyRanking($data, $period)['implemented']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 显示添加表单
        document.getElementById('showAddFormBtn').addEventListener('click', function() {
            document.getElementById('addRecordForm').style.display = 'block';
        });
        
        // 控制追加积分字段显示
        document.getElementById('isAdditional').addEventListener('change', function() {
            const additionalFields = document.getElementById('additionalPointsFields');
            if (this.checked) {
                additionalFields.style.display = 'grid';
            } else {
                additionalFields.style.display = 'none';
            }
        });
        
        document.getElementById('isAdditionalEdit').addEventListener('change', function() {
            const additionalFields = document.getElementById('additionalPointsFieldsEdit');
            if (this.checked) {
                additionalFields.style.display = 'grid';
            } else {
                additionalFields.style.display = 'none';
            }
        });
    </script>
</body>
</html>
