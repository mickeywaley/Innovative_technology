<?php
// 创新积分统计系统 - 精简版

// 数据文件
$dataFile = 'innovation_data.txt';
$userFile = 'users.txt';
$departments = ['售前部', '售后部', '店长运营部', '生产部'];

// 初始化会话
session_start();

// 用户权限常量
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// 工具函数
function loadData($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user']['role'] === ROLE_ADMIN;
}

// 登录处理
if (isset($_POST['login'])) {
    $users = loadData($userFile);
    foreach ($users as $user) {
        if ($user['username'] === $_POST['username'] && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        }
    }
    $loginError = '用户名或密码错误';
}

// 注销
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 确保至少有一个管理员账户
$users = loadData($userFile);
if (empty($users)) {
    $users[] = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => ROLE_ADMIN
    ];
    saveData($userFile, $users);
}

// 只有登录用户才能访问以下功能
if (isLoggedIn()) {
    $data = loadData($dataFile);
    
    // 添加记录
    if (isAdmin() && isset($_POST['add'])) {
        $data[] = [
            'id' => uniqid(),
            'date' => date('Y-m-d'),
            'department' => $_POST['department'],
            'name' => $_POST['name'],
            'content' => $_POST['content'],
            'quantity' => (int)$_POST['quantity'],
            'points' => (int)$_POST['points'],
            'implemented' => isset($_POST['implemented'])
        ];
        saveData($dataFile, $data);
        header('Location: index.php');
        exit;
    }
    
    // 编辑记录
    if (isAdmin() && isset($_POST['edit'])) {
        foreach ($data as &$record) {
            if ($record['id'] === $_POST['id']) {
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
    
    // 删除记录
    if (isAdmin() && isset($_GET['delete'])) {
        $data = array_filter($data, function($record) {
            return $record['id'] !== $_GET['delete'];
        });
        saveData($dataFile, $data);
        header('Location: index.php');
        exit;
    }
    
    // 获取所有唯一姓名
    $uniqueNames = array_unique(array_column($data, 'name'));
    
    // 统计函数
    function getStats($data, $groupBy) {
        $stats = [];
        foreach ($data as $record) {
            $key = $groupBy === 'department' ? $record['department'] : "{$record['department']}-{$record['name']}";
            
            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'department' => $record['department'],
                    'name' => $groupBy === 'department' ? '' : $record['name'],
                    'records' => 0,
                    'quantity' => 0,
                    'points' => 0,
                    'implemented' => 0
                ];
            }
            
            $stats[$key]['records']++;
            $stats[$key]['quantity'] += $record['quantity'];
            $stats[$key]['points'] += $record['points'];
            if ($record['implemented']) {
                $stats[$key]['implemented']++;
            }
        }
        return $stats;
    }
    
    // 获取当前周和月的数据
    $currentWeekStart = date('Y-m-d', strtotime('Monday this week'));
    $currentWeekEnd = date('Y-m-d', strtotime('Sunday this week'));
    $currentMonthStart = date('Y-m-01');
    $currentMonthEnd = date('Y-m-t');
    
    $currentWeekData = array_filter($data, function($record) use ($currentWeekStart, $currentWeekEnd) {
        return $record['date'] >= $currentWeekStart && $record['date'] <= $currentWeekEnd;
    });
    
    $currentMonthData = array_filter($data, function($record) use ($currentMonthStart, $currentMonthEnd) {
        return $record['date'] >= $currentMonthStart && $record['date'] <= $currentMonthEnd;
    });
    
    // 获取视图参数
    $view = isset($_GET['view']) ? $_GET['view'] : 'all';
    
    // 获取编辑记录
    $editRecord = null;
    if (isAdmin() && isset($_GET['edit'])) {
        foreach ($data as $record) {
            if ($record['id'] === $_GET['edit']) {
                $editRecord = $record;
                break;
            }
        }
    }
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
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 导航栏 -->
    <nav class="bg-primary text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fa fa-line-chart mr-2 text-xl"></i>
                    <h1 class="text-xl font-bold">创新积分统计系统</h1>
                </div>
                <?php if (isLoggedIn()): ?>
                <div class="flex items-center">
                    <span class="mr-3"><?php echo $_SESSION['user']['username']; ?> (<?php echo $_SESSION['user']['role'] === ROLE_ADMIN ? '管理员' : '普通用户'; ?>)</span>
                    <a href="?logout" class="py-1 px-3 bg-white text-primary rounded-md hover:bg-gray-100 transition">
                        <i class="fa fa-sign-out mr-1"></i> 退出
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-6">
        <!-- 登录页面 -->
        <?php if (!isLoggedIn()): ?>
        <div class="max-w-md mx-auto mt-12 bg-white rounded-xl p-6 shadow-lg">
            <h2 class="text-2xl font-bold mb-4 text-gray-800 text-center">登录系统</h2>
            <form action="index.php" method="post" class="space-y-4">
                <input type="hidden" name="login" value="1">
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
                <button type="submit" class="w-full py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    <i class="fa fa-sign-in mr-1"></i> 登录
                </button>
            </form>
            <p class="mt-4 text-sm text-gray-500 text-center">
                默认管理员账户：admin / admin123
            </p>
        </div>
        <?php else: ?>
        <!-- 主内容区 -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- 侧边栏 -->
            <div class="lg:col-span-1 space-y-6">
                <!-- 控制面板 -->
                <?php if (isAdmin()): ?>
                <div class="bg-white rounded-xl p-5 shadow-lg">
                    <h2 class="text-lg font-bold mb-3 text-gray-800">控制面板</h2>
                    <div class="space-y-2">
                        <a href="?action=add" class="block py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition flex items-center">
                            <i class="fa fa-plus-circle mr-2"></i> 添加记录
                        </a>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="?view=weekly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                <i class="fa fa-calendar mr-1"></i> 周统计
                            </a>
                            <a href="?view=monthly" class="py-2 px-3 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition text-sm text-center">
                                <i class="fa fa-calendar mr-1"></i> 月统计
                            </a>
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
                <div class="bg-white rounded-xl p-5 shadow-lg">
                    <h2 class="text-lg font-bold mb-3 text-gray-800">统计概览</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                            <div class="text-blue-500 mb-1">
                                <i class="fa fa-list-alt"></i>
                            </div>
                            <div class="text-xl font-bold"><?php echo count($data); ?></div>
                            <div class="text-xs text-gray-500">记录总数</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg border border-green-100">
                            <div class="text-green-500 mb-1">
                                <i class="fa fa-trophy"></i>
                            </div>
                            <div class="text-xl font-bold"><?php echo array_sum(array_column($data, 'points')); ?></div>
                            <div class="text-xs text-gray-500">总积分</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
                            <div class="text-purple-500 mb-1">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <div class="text-xl font-bold"><?php echo count(array_filter($data, function($r) { return $r['implemented']; })); ?></div>
                            <div class="text-xs text-gray-500">已实施</div>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100">
                            <div class="text-yellow-500 mb-1">
                                <i class="fa fa-star"></i>
                            </div>
                            <div class="text-xl font-bold"><?php echo count($data) > 0 ? round(array_sum(array_column($data, 'points')) / count($data), 1) : 0; ?></div>
                            <div class="text-xs text-gray-500">平均积分</div>
                        </div>
                    </div>
                </div>
                
                <!-- 排行统计 -->
                <div class="bg-white rounded-xl p-5 shadow-lg">
                    <h2 class="text-lg font-bold mb-3 text-gray-800">积分排行</h2>
                    
                    <h3 class="text-sm font-semibold mb-2 text-gray-700">本周排行</h3>
                    <div class="space-y-2 mb-4">
                        <?php foreach (array_slice(getStats($currentWeekData, 'person'), 0, 3) as $index => $person): ?>
                        <div class="flex items-center p-2 rounded-lg <?php echo $index == 0 ? 'bg-yellow-50' : ($index == 1 ? 'bg-gray-100' : 'bg-orange-50'); ?>">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center bg-<?php echo $index == 0 ? 'yellow-100 text-yellow-700' : ($index == 1 ? 'gray-100 text-gray-700' : 'orange-100 text-orange-700'); ?> font-bold mr-2">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-sm"><?php echo $person['name'] ?> (<?php echo $person['department'] ?>)</div>
                                <div class="text-xs text-gray-500">积分: <?php echo $person['points'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3 class="text-sm font-semibold mb-2 text-gray-700">本月排行</h3>
                    <div class="space-y-2">
                        <?php foreach (array_slice(getStats($currentMonthData, 'person'), 0, 3) as $index => $person): ?>
                        <div class="flex items-center p-2 rounded-lg <?php echo $index == 0 ? 'bg-yellow-50' : ($index == 1 ? 'bg-gray-100' : 'bg-orange-50'); ?>">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center bg-<?php echo $index == 0 ? 'yellow-100 text-yellow-700' : ($index == 1 ? 'gray-100 text-gray-700' : 'orange-100 text-orange-700'); ?> font-bold mr-2">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-sm"><?php echo $person['name'] ?> (<?php echo $person['department'] ?>)</div>
                                <div class="text-xs text-gray-500">积分: <?php echo $person['points'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- 主内容 -->
            <div class="lg:col-span-3 space-y-6">
                <!-- 添加/编辑表单 -->
                <?php if (isAdmin() && (isset($_GET['action']) && $_GET['action'] == 'add' || isset($editRecord))): ?>
                <div class="bg-white rounded-xl p-5 shadow-lg">
                    <h2 class="text-lg font-bold mb-4 text-gray-800">
                        <?php echo isset($editRecord) ? '编辑创新记录' : '添加创新记录'; ?>
                    </h2>
                    <form action="index.php" method="post" class="space-y-4">
                        <input type="hidden" name="<?php echo isset($editRecord) ? 'edit' : 'add'; ?>" value="1">
                        <?php if (isset($editRecord)): ?>
                        <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">部门</label>
                                <select id="department" name="department" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo isset($editRecord) && $editRecord['department'] == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                                <input type="text" id="name" name="name" required list="namesList"
                                    value="<?php echo isset($editRecord) ? htmlspecialchars($editRecord['name']) : ''; ?>"
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
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"><?php echo isset($editRecord) ? htmlspecialchars($editRecord['content']) : ''; ?></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">创新数量</label>
                                <input type="number" id="quantity" name="quantity" min="1" required
                                    value="<?php echo isset($editRecord) ? $editRecord['quantity'] : 1; ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                            </div>
                            <div>
                                <label for="points" class="block text-sm font-medium text-gray-700 mb-1">创新积分</label>
                                <input type="number" id="points" name="points" min="1" required
                                    value="<?php echo isset($editRecord) ? $editRecord['points'] : 1; ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition">
                            </div>
                            <div class="flex items-end">
                                <label for="implemented" class="flex items-center">
                                    <input type="checkbox" id="implemented" name="implemented" class="mr-2 h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                                        <?php echo isset($editRecord) && $editRecord['implemented'] ? 'checked' : ''; ?>>
                                    已落地实施
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <a href="index.php" class="py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                                取消
                            </a>
                            <button type="submit" class="py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                                <i class="fa fa-save mr-1"></i> 保存
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- 数据展示 -->
                <div class="bg-white rounded-xl p-5 shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-800">
                            <?php 
                            switch ($view) {
                                case 'weekly': echo '周统计数据'; break;
                                case 'monthly': echo '月统计数据'; break;
                                case 'department': echo '部门统计数据'; break;
                                case 'person': echo '个人统计数据'; break;
                                default: echo '所有创新记录'; break;
                            }
                            ?>
                        </h2>
                        <?php if (isAdmin() && $view == 'all'): ?>
                        <a href="?action=add" class="py-2 px-4 bg-success text-white rounded-lg hover:bg-success/90 transition text-sm">
                            <i class="fa fa-plus-circle mr-1"></i> 添加记录
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($view == 'all'): ?>
                    <!-- 所有记录 -->
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                                    <?php if (isAdmin()): ?>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($data as $record): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['date']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['department']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['name']; ?></td>
                                    <td class="px-6 py-4 max-w-xs truncate"><?php echo htmlspecialchars($record['content']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['quantity']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['points']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $record['implemented'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $record['implemented'] ? '已实施' : '未实施'; ?>
                                        </span>
                                    </td>
                                    <?php if (isAdmin()): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?edit=<?php echo $record['id']; ?>" class="text-primary hover:text-primary/80 mr-3">
                                            <i class="fa fa-pencil"></i> 编辑
                                        </a>
                                        <a href="?delete=<?php echo $record['id']; ?>" class="text-danger hover:text-danger/80" onclick="return confirm('确定要删除此记录吗？')">
                                            <i class="fa fa-trash"></i> 删除
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif ($view == 'weekly' || $view == 'monthly'): ?>
                    <!-- 周/月统计 -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <?php echo $view == 'weekly' ? '周' : '月'; ?>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新记录数</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新数量</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创新积分</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已实施数</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                $periods = [];
                                $groupBy = $view == 'weekly' ? 'W' : 'm';
                                $format = $view == 'weekly' ? 'Y-W' : 'Y-m';
                                
                                foreach ($data as $record) {
                                    $period = date($format, strtotime($record['date']));
                                    if (!isset($periods[$period])) {
                                        $periods[$period] = [
                                            'records' => 0,
                                            'quantity' => 0,
                                            'points' => 0,
                                            'implemented' => 0
                                        ];
                                    }
                                    $periods[$period]['records']++;
                                    $periods[$period]['quantity'] += $record['quantity'];
                                    $periods[$period]['points'] += $record['points'];
                                    if ($record['implemented']) {
                                        $periods[$period]['implemented']++;
                                    }
                                }
                                
                                // 排序
                                ksort($periods);
                                
                                foreach ($periods as $period => $stats):
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $period; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['records']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['quantity']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['points']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $stats['implemented']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif ($view == 'department'): ?>
                    <!-- 部门统计 -->
                    <div class="overflow-x-auto">
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
                                $deptStats = getStats($data, 'department');
                                
                                // 按积分排序
                                uasort($deptStats, function($a, $b) {
                                    return $b['points'] - $a['points'];
                                });
                                
                                foreach ($deptStats as $dept => $stats):
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $dept; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['records']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['quantity']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['points']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $stats['implemented']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif ($view == 'person'): ?>
                    <!-- 个人统计 -->
                    <div class="overflow-x-auto">
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
                                $personStats = getStats($data, 'person');
                                
                                // 按积分排序
                                uasort($personStats, function($a, $b) {
                                    return $b['points'] - $a['points'];
                                });
                                
                                foreach ($personStats as $key => $stats):
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['name'] ?> (<?php echo $stats['department'] ?>)</td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['records']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['quantity']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $stats['points']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $stats['implemented']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white py-6 mt-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>创新积分统计系统 &copy; 2025</p>
            </div>
        </div>
    </footer>

    <script>
        // 表单切换逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const showAddFormBtn = document.getElementById('showAddFormBtn');
            const addRecordForm = document.getElementById('addRecordForm');
            const dataDisplay = document.getElementById('dataDisplay');
            
            if (showAddFormBtn && addRecordForm && dataDisplay) {
                showAddFormBtn.addEventListener('click', function() {
                    addRecordForm.classList.remove('hidden');
                    dataDisplay.classList.add('hidden');
                });
            }
            
            // 平滑滚动
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
        });
    </script>
</body>
</html>
