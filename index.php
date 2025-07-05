<?php
// 创新积分统计系统 - 单文件精简版

// 数据文件
$dataFile = 'innovation_data.txt';
$userFile = 'users.txt';
$departments = ['售前部', '售后部', '店长运营部', '生产部'];

// 初始化用户会话
session_start();

// 用户权限常量
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// 加载数据
function loadData($file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// 保存数据
function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// 检查用户是否登录
function isLoggedIn() {
    return isset($_SESSION['user']);
}

// 检查用户是否为管理员
function isAdmin() {
    return isLoggedIn() && $_SESSION['user']['role'] === ROLE_ADMIN;
}

// 登录处理
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $users = loadData($userFile);
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        }
    }
    
    $loginError = '用户名或密码错误';
}

// 注销处理
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 添加用户（仅限管理员）
if (isset($_POST['action']) && $_POST['action'] == 'addUser' && isAdmin()) {
    $users = loadData($userFile);
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    // 检查用户名是否已存在
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            $addUserError = '用户名已存在';
            break;
        }
    }
    
    if (!isset($addUserError)) {
        $newUser = [
            'username' => $username,
            'password' => $password,
            'role' => $role
        ];
        
        $users[] = $newUser;
        saveData($userFile, $users);
        header('Location: index.php?action=manageUsers');
        exit;
    }
}

// 删除用户（仅限管理员）
if (isset($_GET['action']) && $_GET['action'] == 'deleteUser' && isAdmin()) {
    $users = loadData($userFile);
    $username = $_GET['username'];
    
    // 不能删除自己
    if ($username === $_SESSION['user']['username']) {
        $deleteUserError = '不能删除自己';
    } else {
        $users = array_filter($users, function($user) use ($username) {
            return $user['username'] !== $username;
        });
        
        saveData($userFile, $users);
        header('Location: index.php?action=manageUsers');
        exit;
    }
}

// 添加记录（仅限管理员）
if (isset($_POST['action']) && $_POST['action'] == 'add' && isAdmin()) {
    $data = loadData($dataFile);
    
    $record = [
        'id' => uniqid(),
        'date' => date('Y-m-d'),
        'department' => $_POST['department'],
        'name' => $_POST['name'],
        'content' => $_POST['content'],
        'quantity' => (int)$_POST['quantity'],
        'points' => (int)$_POST['points'],
        'implemented' => isset($_POST['implemented'])
    ];
    
    $data[] = $record;
    saveData($dataFile, $data);
    
    header('Location: index.php');
    exit;
}

// 更新记录（仅限管理员）
if (isset($_POST['action']) && $_POST['action'] == 'update' && isAdmin()) {
    $data = loadData($dataFile);
    
    foreach ($data as &$record) {
        if ($record['id'] == $_POST['id']) {
            $record['department'] = $_POST['department'];
            $record['name'] = $_POST['name'];
            $record['content'] = $_POST['content'];
            $record['quantity'] = (int)$_POST['quantity'];
            $record['points'] = (int)$_POST['points'];
            $record['implemented'] = isset($_POST['implemented']);
            break;
        }
    }
    
    saveData($dataFile, $data);
    
    header('Location: index.php');
    exit;
}

// 删除记录（仅限管理员）
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isAdmin()) {
    $data = loadData($dataFile);
    
    $data = array_filter($data, function($record) {
        return $record['id'] != $_GET['id'];
    });
    
    saveData($dataFile, $data);
    
    header('Location: index.php');
    exit;
}

// 获取数据
$data = loadData($dataFile);

// 获取所有唯一姓名
$uniqueNames = [];
foreach ($data as $record) {
    $uniqueNames[$record['name']] = true;
}
$uniqueNames = array_keys($uniqueNames);

// 统计函数
function getWeeklyStats($data) {
    $weeks = [];
    foreach ($data as $record) {
        $weekNum = date('W', strtotime($record['date']));
        $year = date('Y', strtotime($record['date']));
        $key = "$year-W$weekNum";
        
        if (!isset($weeks[$key])) {
            $weeks[$key] = [
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $weeks[$key]['records']++;
        $weeks[$key]['quantity'] += $record['quantity'];
        $weeks[$key]['points'] += $record['points'];
        if ($record['implemented']) {
            $weeks[$key]['implemented']++;
        }
    }
    return $weeks;
}

function getMonthlyStats($data) {
    $months = [];
    foreach ($data as $record) {
        $month = date('Y-m', strtotime($record['date']));
        
        if (!isset($months[$month])) {
            $months[$month] = [
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $months[$month]['records']++;
        $months[$month]['quantity'] += $record['quantity'];
        $months[$month]['points'] += $record['points'];
        if ($record['implemented']) {
            $months[$month]['implemented']++;
        }
    }
    return $months;
}

function getQuarterlyStats($data) {
    $quarters = [];
    foreach ($data as $record) {
        $year = date('Y', strtotime($record['date']));
        $month = (int)date('n', strtotime($record['date']));
        $quarter = ceil($month / 3);
        $key = "$year-Q$quarter";
        
        if (!isset($quarters[$key])) {
            $quarters[$key] = [
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $quarters[$key]['records']++;
        $quarters[$key]['quantity'] += $record['quantity'];
        $quarters[$key]['points'] += $record['points'];
        if ($record['implemented']) {
            $quarters[$key]['implemented']++;
        }
    }
    return $quarters;
}

function getYearlyStats($data) {
    $years = [];
    foreach ($data as $record) {
        $year = date('Y', strtotime($record['date']));
        
        if (!isset($years[$year])) {
            $years[$year] = [
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $years[$year]['records']++;
        $years[$year]['quantity'] += $record['quantity'];
        $years[$year]['points'] += $record['points'];
        if ($record['implemented']) {
            $years[$year]['implemented']++;
        }
    }
    return $years;
}

function getDepartmentStats($data) {
    $departments = [];
    foreach ($data as $record) {
        $dept = $record['department'];
        
        if (!isset($departments[$dept])) {
            $departments[$dept] = [
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $departments[$dept]['records']++;
        $departments[$dept]['quantity'] += $record['quantity'];
        $departments[$dept]['points'] += $record['points'];
        if ($record['implemented']) {
            $departments[$dept]['implemented']++;
        }
    }
    return $departments;
}

function getPersonStats($data) {
    $persons = [];
    foreach ($data as $record) {
        $key = "{$record['department']}-{$record['name']}";
        
        if (!isset($persons[$key])) {
            $persons[$key] = [
                'department' => $record['department'],
                'name' => $record['name'],
                'records' => 0,
                'quantity' => 0,
                'points' => 0,
                'implemented' => 0
            ];
        }
        
        $persons[$key]['records']++;
        $persons[$key]['quantity'] += $record['quantity'];
        $persons[$key]['points'] += $record['points'];
        if ($record['implemented']) {
            $persons[$key]['implemented']++;
        }
    }
    return $persons;
}

// 获取当前周和月的开始和结束日期
$currentDate = new DateTime();
$currentWeekStart = clone $currentDate;
$currentWeekStart->modify('Monday this week');
$currentWeekEnd = clone $currentWeekStart;
$currentWeekEnd->modify('+6 days');

$currentMonthStart = clone $currentDate;
$currentMonthStart->modify('first day of this month');
$currentMonthEnd = clone $currentDate;
$currentMonthEnd->modify('last day of this month');

// 获取当前周和月的数据
$currentWeekData = array_filter($data, function($record) use ($currentWeekStart, $currentWeekEnd) {
    $recordDate = new DateTime($record['date']);
    return $recordDate >= $currentWeekStart && $recordDate <= $currentWeekEnd;
});

$currentMonthData = array_filter($data, function($record) use ($currentMonthStart, $currentMonthEnd) {
    $recordDate = new DateTime($record['date']);
    return $recordDate >= $currentMonthStart && $recordDate <= $currentMonthEnd;
});

// 计算当前周和月的排行
$currentWeekRanking = getPersonStats($currentWeekData);
$currentMonthRanking = getPersonStats($currentMonthData);

// 按积分排序
usort($currentWeekRanking, function($a, $b) {
    return $b['points'] - $a['points'];
});

usort($currentMonthRanking, function($a, $b) {
    return $b['points'] - $a['points'];
});

// 获取当前查看的统计类型
$view = isset($_GET['view']) ? $_GET['view'] : 'all';

// 获取要编辑的记录
$editRecord = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isAdmin()) {
    foreach ($data as $record) {
        if ($record['id'] == $_GET['id']) {
            $editRecord = $record;
            break;
        }
    }
}

// 检查是否需要初始化用户
$users = loadData($userFile);
if (empty($users)) {
    // 创建默认管理员账户
    $defaultAdmin = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => ROLE_ADMIN
    ];
    
    $users[] = $defaultAdmin;
    saveData($userFile, $users);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创新积分统计系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#64748B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
                        info: '#06B6D4',
                        light: '#F8FAFC',
                        dark: '#1E293B'
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .transition-height {
                transition-property: height;
                transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
                transition-duration: 300ms;
            }
            .card-shadow {
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans">
    <header class="bg-primary text-white shadow-lg">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-[clamp(1.8rem,3vw,2.5rem)] font-bold flex items-center">
                        <i class="fa fa-line-chart mr-3"></i>
                        创新积分统计系统
                    </h1>
                    <p class="mt-2 text-blue-100">高效跟踪和分析团队创新成果</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <?php if (isLoggedIn()): ?>
                        <div class="flex items-center">
                            <span class="mr-3 font-medium">欢迎，<?php echo $_SESSION['user']['username']; ?> (<?php echo $_SESSION['user']['role'] === ROLE_ADMIN ? '管理员' : '普通用户'; ?>)</span>
                            <a href="?action=logout" class="py-2 px-4 bg-white text-primary rounded-lg hover:bg-gray-100 transition text-sm">
                                <i class="fa fa-sign-out mr-1"></i> 退出
                            </a>
                            <?php if (isAdmin()): ?>
                                <a href="?action=manageUsers" class="ml-2 py-2 px-4 bg-warning text-white rounded-lg hover:bg-warning/90 transition text-sm">
                                    <i class="fa fa-users mr-1"></i> 管理用户
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <a href="?action=login" class="py-2 px-4 bg-white text-primary rounded-lg hover:bg-gray-100 transition text-sm">
                            <i class="fa fa-sign-in mr-1"></i> 登录
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <!-- 登录表单 -->
        <?php if (isset($_GET['action']) && $_GET['action'] == 'login' && !isLoggedIn()): ?>
            <div class="max-w-md mx-auto bg-white rounded-xl p-6 shadow-lg">
                <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                    <i class="fa fa-sign-in mr-2 text-primary"></i>
                    登录系统
                </h2>
                <form action="index.php" method="post" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                        <input type="text" id="username" name="username" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                    </div>
                    <?php if (isset($loginError)): ?>
                        <div class="text-danger text-sm"><?php echo $loginError; ?></div>
                    <?php endif; ?>
                    <div class="flex justify-end">
                        <button type="submit" class="py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                            <i class="fa fa-check mr-1"></i> 登录
                        </button>
                    </div>
                </form>
                <p class="mt-4 text-sm text-gray-500">
                    默认管理员账户：admin / admin123
                </p>
            </div>
        <?php elseif (isset($_GET['action']) && $_GET['action'] == 'manageUsers' && isAdmin()): ?>
            <!-- 用户管理页面 -->
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fa fa-users mr-2 text-warning"></i>
                        用户管理
                    </h2>
                    <button id="showAddUserFormBtn" class="py-2 px-4 bg-success text-white rounded-lg hover:bg-success/90 transition text-sm">
                        <i class="fa fa-plus-circle mr-1"></i> 添加用户
                    </button>
                </div>
                
                <!-- 添加用户表单 -->
                <div id="addUserForm" class="mb-6 hidden">
                    <h3 class="text-lg font-semibold mb-3 text-gray-700">添加用户</h3>
                    <form action="index.php" method="post" class="space-y-4">
                        <input type="hidden" name="action" value="addUser">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
                                <input type="text" id="username" name="username" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                            </div>
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">角色</label>
                            <select id="role" name="role" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                <option value="">请选择角色</option>
                                <option value="admin">管理员</option>
                                <option value="user">普通用户</option>
                            </select>
                        </div>
                        <?php if (isset($addUserError)): ?>
                            <div class="text-danger text-sm"><?php echo $addUserError; ?></div>
                        <?php endif; ?>
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelAddUserBtn" class="py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                                取消
                            </button>
                            <button type="submit" class="py-2 px-4 bg-success text-white rounded-lg hover:bg-success/90 transition">
                                <i class="fa fa-save mr-1"></i> 添加用户
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 用户列表 -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用户名</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $users = loadData($userFile);
                            foreach ($users as $user):
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $user['username']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === ROLE_ADMIN ? 'bg-warning text-white' : 'bg-info text-white'; ?>">
                                        <?php echo $user['role'] === ROLE_ADMIN ? '管理员' : '普通用户'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($user['username'] !== $_SESSION['user']['username']): ?>
                                        <a href="?action=deleteUser&username=<?php echo $user['username']; ?>" class="text-danger hover:text-danger/80" onclick="return confirm('确定要删除此用户吗？')">
                                            <i class="fa fa-trash"></i> 删除
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- 主内容区域 -->
            <?php if (!isLoggedIn()): ?>
                <div class="max-w-md mx-auto bg-white rounded-xl p-6 shadow-lg text-center">
                    <div class="mb-4 text-4xl text-primary">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h2 class="text-xl font-bold mb-2 text-gray-800">请先登录</h2>
                    <p class="text-gray-600 mb-4">您需要登录才能查看创新积分统计数据</p>
                    <a href="?action=login" class="inline-block py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                        <i class="fa fa-sign-in mr-1"></i> 登录
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- 侧边栏 -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- 控制面板 -->
                        <?php if (isAdmin()): ?>
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                                <i class="fa fa-cog mr-2 text-primary"></i>
                                控制面板
                            </h2>
                            <div class="space-y-3">
                                <button id="showAddFormBtn" class="w-full py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition flex items-center justify-center">
                                    <i class="fa fa-plus-circle mr-2"></i>
                                    添加创新记录
                                </button>
                                <div class="grid grid-cols-2 gap-2">
                                    <a href="?view=weekly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                        <i class="fa fa-calendar mr-1"></i> 周统计
                                    </a>
                                    <a href="?view=monthly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                        <i class="fa fa-calendar mr-1"></i> 月统计
                                    </a>
                                    <a href="?view=quarterly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                        <i class="fa fa-calendar mr-1"></i> 季统计
                                    </a>
                                    <a href="?view=yearly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                        <i class="fa fa-calendar mr-1"></i> 年统计
                                    </a>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <a href="?view=department" class="py-2 px-3 bg-info text-white rounded-lg hover:bg-info/90 transition text-sm text-center">
                                        <i class="fa fa-building mr-1"></i> 部门统计
                                    </a>
                                    <a href="?view=person" class="py-2 px-3 bg-info text-white rounded-lg hover:bg-info/90 transition text-sm text-center">
                                        <i class="fa fa-user mr-1"></i> 个人统计
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 统计概览 -->
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                                <i class="fa fa-bar-chart mr-2 text-primary"></i>
                                统计概览
                            </h2>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                    <div class="text-blue-500 mb-1">
                                        <i class="fa fa-list-alt text-xl"></i>
                                    </div>
                                    <div class="text-2xl font-bold"><?php echo count($data); ?></div>
                                    <div class="text-sm text-gray-500">创新记录总数</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                                    <div class="text-green-500 mb-1">
                                        <i class="fa fa-trophy text-xl"></i>
                                    </div>
                                    <div class="text-2xl font-bold"><?php echo array_sum(array_column($data, 'points')); ?></div>
                                    <div class="text-sm text-gray-500">总创新积分</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                                    <div class="text-purple-500 mb-1">
                                        <i class="fa fa-check-circle text-xl"></i>
                                    </div>
                                    <div class="text-2xl font-bold"><?php 
                                        $implemented = array_filter($data, function($record) {
                                            return $record['implemented'];
                                        });
                                        echo count($implemented);
                                    ?></div>
                                    <div class="text-sm text-gray-500">已实施数</div>
                                </div>
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                    <div class="text-yellow-500 mb-1">
                                        <i class="fa fa-star text-xl"></i>
                                    </div>
                                    <div class="text-2xl font-bold"><?php 
                                        $avgPoints = count($data) > 0 ? array_sum(array_column($data, 'points')) / count($data) : 0;
                                        echo round($avgPoints, 1);
                                    ?></div>
                                    <div class="text-sm text-gray-500">平均积分</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 排行统计 -->
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                                <i class="fa fa-trophy mr-2 text-warning"></i>
                                积分排行
                            </h2>
                            
                            <h3 class="text-lg font-semibold mb-2 text-gray-700">本周排行</h3>
                            <div class="space-y-2 mb-4">
                                <?php foreach (array_slice($currentWeekRanking, 0, 3) as $index => $person): ?>
                                <div class="flex items-center p-2 rounded-lg <?php echo $index == 0 ? 'bg-yellow-50' : ($index == 1 ? 'bg-gray-50' : 'bg-orange-50'); ?>">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center bg-<?php echo $index == 0 ? 'yellow-100 text-yellow-700' : ($index == 1 ? 'gray-100 text-gray-700' : 'orange-100 text-orange-700'); ?> font-bold mr-2">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium"><?php echo $person['name'] ?> (<?php echo $person['department'] ?>)</div>
                                        <div class="text-sm text-gray-500">积分: <?php echo $person['points'] ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <h3 class="text-lg font-semibold mb-2 text-gray-700">本月排行</h3>
                            <div class="space-y-2">
                                <?php foreach (array_slice($currentMonthRanking, 0, 3) as $index => $person): ?>
                                <div class="flex items-center p-2 rounded-lg <?php echo $index == 0 ? 'bg-yellow-50' : ($index == 1 ? 'bg-gray-50' : 'bg-orange-50'); ?>">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center bg-<?php echo $index == 0 ? 'yellow-100 text-yellow-700' : ($index == 1 ? 'gray-100 text-gray-700' : 'orange-100 text-orange-700'); ?> font-bold mr-2">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium"><?php echo $person['name'] ?> (<?php echo $person['department'] ?>)</div>
                                        <div class="text-sm text-gray-500">积分: <?php echo $person['points'] ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 主内容区 -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- 添加记录表单 -->
                        <?php if (isAdmin()): ?>
                        <div id="addRecordForm" class="bg-white rounded-xl p-6 shadow-lg <?php echo isset($_GET['action']) && $_GET['action'] == 'add' ? '' : 'hidden'; ?>">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                                <i class="fa fa-plus-circle mr-2 text-success"></i>
                                添加创新记录
                            </h2>
                            <form action="index.php" method="post" class="space-y-4">
                                <input type="hidden" name="action" value="add">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">部门</label>
                                        <select id="department" name="department" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                            <option value="">请选择部门</option>
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                                        <input type="text" id="name" name="name" required list="namesList"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                        <datalist id="namesList">
                                            <?php foreach ($uniqueNames as $name): ?>
                                            <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-1">创新内容</label>
                                    <textarea id="content" name="content" rows="3" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"></textarea>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">创新数量</label>
                                        <input type="number" id="quantity" name="quantity" min="1" value="1" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                    </div>
                                    <div>
                                        <label for="points" class="block text-sm font-medium text-gray-700 mb-1">创新积分</label>
                                        <input type="number" id="points" name="points" min="1" value="1" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                    </div>
                                    <div class="flex items-end">
                                        <label for="implemented" class="flex items-center">
                                            <input type="checkbox" id="implemented" name="implemented" class="mr-2 h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                            已落地实施
                                        </label>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <a href="index.php" class="py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                                        取消
                                    </a>
                                    <button type="submit" class="py-2 px-4 bg-success text-white rounded-lg hover:bg-success/90 transition">
                                        <i class="fa fa-save mr-1"></i> 保存记录
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 编辑记录表单 -->
                        <div id="editRecordForm" class="bg-white rounded-xl p-6 shadow-lg <?php echo isset($editRecord) ? '' : 'hidden'; ?>">
                            <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
                                <i class="fa fa-pencil mr-2 text-primary"></i>
                                编辑创新记录
                            </h2>
                            <form action="index.php" method="post" class="space-y-4">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">部门</label>
                                        <select id="department" name="department" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept; ?>" <?php echo $editRecord['department'] == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                                        <input type="text" id="name" name="name" required list="namesList" value="<?php echo htmlspecialchars($editRecord['name']); ?>"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                        <datalist id="namesList">
                                            <?php foreach ($uniqueNames as $name): ?>
                                            <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-1">创新内容</label>
                                    <textarea id="content" name="content" rows="3" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"><?php echo htmlspecialchars($editRecord['content']); ?></textarea>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">创新数量</label>
                                        <input type="number" id="quantity" name="quantity" min="1" value="<?php echo $editRecord['quantity']; ?>" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                    </div>
                                    <div>
                                        <label for="points" class="block text-sm font-medium text-gray-700 mb-1">创新积分</label>
                                        <input type="number" id="points" name="points" min="1" value="<?php echo $editRecord['points']; ?>" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                    </div>
                                    <div class="flex items-end">
                                        <label for="implemented" class="flex items-center">
                                            <input type="checkbox" id="implemented" name="implemented" class="mr-2 h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" <?php echo $editRecord['implemented'] ? 'checked' : ''; ?>>
                                            已落地实施
                                        </label>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <a href="index.php" class="py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                                        取消
                                    </a>
                                    <button type="submit" class="py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                                        <i class="fa fa-save mr-1"></i> 更新记录
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- 数据展示区域 -->
                        <div id="dataDisplay" class="bg-white rounded-xl p-6 shadow-lg">
                            <div class="flex justify-between items-center mb-4">
                                <h2 id="displayTitle" class="text-xl font-bold text-gray-800 flex items-center">
                                    <i class="fa fa-table mr-2 text-primary"></i>
                                    <?php 
                                    switch ($view) {
                                        case 'weekly': echo '周统计数据'; break;
                                        case 'monthly': echo '月统计数据'; break;
                                        case 'quarterly': echo '季度统计数据'; break;
                                        case 'yearly': echo '年统计数据'; break;
                                        case 'department': echo '部门统计数据'; break;
                                        case 'person': echo '个人统计数据'; break;
                                        default: echo '所有记录'; break;
                                    }
                                    ?>
                                </h2>
                                <?php if (isAdmin()): ?>
                                <div>
                                    <a href="?action=add" class="py-2 px-4 bg-success text-white rounded-lg hover:bg-success/90 transition text-sm">
                                        <i class="fa fa-plus-circle mr-1"></i> 添加记录
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- 统计数据显示 -->
                            <?php if ($view == 'weekly' || $view == 'monthly' || $view == 'quarterly' || $view == 'yearly'): ?>
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    <?php echo $view == 'weekly' ? '周' : ($view == 'monthly' ? '月' : ($view == 'quarterly' ? '季度' : '年')); ?>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新记录数</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新数量</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新积分</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已实施数</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $stats = [];
                                            switch ($view) {
                                                case 'weekly': $stats = getWeeklyStats($data); break;
                                                case 'monthly': $stats = getMonthlyStats($data); break;
                                                case 'quarterly': $stats = getQuarterlyStats($data); break;
                                                case 'yearly': $stats = getYearlyStats($data); break;
                                            }
                                            
                                            // 按时间排序
                                            if ($view == 'weekly' || $view == 'monthly' || $view == 'quarterly') {
                                                ksort($stats);
                                            } else {
                                                krsort($stats); // 年份倒序
                                            }
                                            
                                            foreach ($stats as $period => $stat):
                                            ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $period; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['records']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['quantity']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['points']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo $stat['implemented']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($view == 'department'): ?>
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部门</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新记录数</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新数量</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新积分</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已实施数</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $deptStats = getDepartmentStats($data);
                                            
                                            // 按积分排序
                                            uasort($deptStats, function($a, $b) {
                                                return $b['points'] - $a['points'];
                                            });
                                            
                                            foreach ($deptStats as $dept => $stat):
                                            ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $dept; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['records']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['quantity']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['points']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo $stat['implemented']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($view == 'person'): ?>
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部门-姓名</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新记录数</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新数量</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新积分</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已实施数</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $personStats = getPersonStats($data);
                                            
                                            // 按积分排序
                                            uasort($personStats, function($a, $b) {
                                                return $b['points'] - $a['points'];
                                            });
                                            
                                            foreach ($personStats as $key => $stat):
                                            ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['name'] ?> (<?php echo $stat['department'] ?>)</td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['records']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['quantity']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['points']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo $stat['implemented']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <!-- 表格区域 -->
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">日期</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部门</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">姓名</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新内容</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">数量</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">积分</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">实施状态</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            if (empty($data)) {
                                                echo '<tr><td colspan="8" class="px-6 py-10 text-center text-gray-500">暂无记录</td></tr>';
                                            } else {
                                                // 按日期倒序排列
                                                usort($data, function($a, $b) {
                                                    return strtotime($b['date']) - strtotime($a['date']);
                                                });
                                                
                                                foreach ($data as $record) {
                                                    echo '<tr class="hover:bg-gray-50 transition">';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">'.$record['date'].'</td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">'.$record['department'].'</td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">'.$record['name'].'</td>';
                                                    echo '<td class="px-6 py-4"><div class="truncate max-w-xs">'.htmlspecialchars($record['content']).'</div></td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">'.$record['quantity'].'</td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">'.$record['points'].'</td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap">';
                                                    echo '<span class="px-2 py-1 text-xs font-semibold rounded-full '.($record['implemented'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800').'">';
                                                    echo $record['implemented'] ? '已实施' : '未实施';
                                                    echo '</span></td>';
                                                    echo '<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">';
                                                    if (isAdmin()) {
                                                        echo '<a href="?action=edit&id='.$record['id'].'" class="text-primary hover:text-primary/80 mr-3">';
                                                        echo '<i class="fa fa-pencil"></i> 编辑';
                                                        echo '</a>';
                                                        echo '<a href="?action=delete&id='.$record['id'].'" class="text-danger hover:text-danger/80" onclick="return confirm(\'确定要删除这条记录吗？\')">';
                                                        echo '<i class="fa fa-trash"></i> 删除';
                                                        echo '</a>';
                                                    } else {
                                                        echo '<span class="text-gray-400">无权限操作</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-bold flex items-center">
                        <i class="fa fa-line-chart mr-2"></i>
                        创新积分统计系统
                    </h2>
                    <p class="text-gray-400 mt-1">高效跟踪和分析团队创新成果</p>
                </div>
                <div class="text-gray-400 text-sm">
                    &copy; 2025 创新积分统计系统 | 设计与开发
                </div>
            </div>
        </div>
    </footer>

    <script>
        // 显示添加表单
        document.getElementById('showAddFormBtn').addEventListener('click', function() {
            document.getElementById('addRecordForm').classList.remove('hidden');
        });
        
        // 显示添加用户表单
        document.getElementById('showAddUserFormBtn').addEventListener('click', function() {
            document.getElementById('addUserForm').classList.remove('hidden');
        });
        
        // 取消添加用户
        document.getElementById('cancelAddUserBtn').addEventListener('click', function() {
            document.getElementById('addUserForm').classList.add('hidden');
        });
    </script>
</body>
</html>    
