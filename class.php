<?php



use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\Grid\Options;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\UserUtils;
use Bitrix\Main\UserTable;
use Bitrix\Intranet\UserAbsence;
use Bitrix\Bizproc\Service\User;


class UserListComponent extends CBitrixComponent {

    private $userList = 'user_list';
    private $modules = ['intranet', 'timeman', 'iblock', 'crm', 'bizproc'];
    

    public function includeModules() {
        foreach($this->modules as $module) {
            Loader::includeModule($module);
        }
    }

    public function setFilterHeaders() {
        return [
            [
                'id' => 'ID',
                'name' => 'ID',
            ],
            [
                'id' => 'FULL_NAME',
                'name' => 'ФИО...',
                'type' => 'custom_entity',
                'default' => true,
                'params' => [
                    'type' => 'user'
                ]

            ],
            [
                'id' => 'DATE',
                'name' => 'Дата',
                'type' => 'date',
                'default' => true
            ],
            [
                'id' => 'WORK_POSITION',
                'name' => 'Должность'
            ],
            [
                'id' => 'WORK_PHONE',
                'name' => 'Рабочий телефон'
            ],
            [
                'id' => 'USER_STATUS',
                'name' => 'Статус пользователя',
                'type' => 'list',
                'items' => [
                    '' => 'Любой',
                    'work' => 'работает',
                    'not_work' => 'не работает',
                    'vacation' => 'в отпуске',
                    'absense' => 'отсутствует (иная причина)'
                ],
                'params' => [
                    'multiple' => 'Y'
                ]
            ]
        ];
    }

    public function setFilterOptions() {
        $this->arResult['FILTER_OPTIONS'] = [
            'FILTER_ID' => $this->userList,
            'GRID_ID' => $this->userList,
            'ENABLE_LIVE_SEARCH' => true,
            'ENABLE_LABEL' => true,
            'FILTER' => $this->setFilterHeaders()
        ];
    }

    public function setGridColumns() {
        return [
            ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
            ['id' => 'FULL_NAME', 'name' => 'ФИО', 'sort' => 'FULL_NAME', 'default' => true],
            ['id' => 'WORK_POSITION', 'name' => 'Должность', 'sort' => 'WORK_POSITION', 'default' => true],
            ['id' => 'WORK_PHONE', 'name' => 'Рабочий телефон', 'sort' => 'WORK_PHONE', 'default' => true],
            ['id' => 'MANAGER', 'name' => 'Руководитель', 'sort' => 'MANAGER', 'default' => true],
            ['id' => 'SUBORDINATE_USERS_COUNT', 'name' => 'Количество подчинённых', 'sort' => 'SUBORDINATE_USERS_COUNT', 'default' => true],
            ['id' => 'WORK_STATUS', 'name' => 'Статус пользователя', 'sort' => 'WORK_STATUS', 'default' => true],
        ];
    }

    public function setGridPageSizes() {
        $sizes = [10, 30, 50, 100, 'Все'];
        $result = [];
        foreach($sizes as $size) {
            $result[] = [
                'NAME' => $size,
                'VALUE' => $size
            ];
        }
        return $result;
    }

    public function prepareUserBaloonHtml($userId, $userName) {
        $params = [
            'USER_ID' => $userId,
            'USER_NAME' => $userName,
            'USER_PROFILE_URL' => Option::get('intranet', 'path_user', '', SITE_ID)
        ];
        return CCrmViewHelper::prepareUserBaloonHtml($params);
    }

    public function getUsers() {

        $sort = ['id' => 'asc'];
        $tmp = 'asc';

        $users = CUser::GetList($sort, $tmp, $filter, [
            'SELECT' => ['UF_DEPARTMENT'],
            'FIELDS' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'ACTIVE', 'WORK_POSITION', 'WORK_PHONE', 'UF_DEPARTMENT']
        ]);
        $arUsers = [];

        while($user = $users->Fetch()) {

            $userId = (int)$user['ID'];
            
            if(!empty($user['UF_DEPARTMENT'])) {

                $arUsers[$userId] = [
                    'id' => $userId,
                    'name' => $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'] ?? '',
                    'work_status' => $user['ACTIVE'] === 'Y' ? 'работает' : 'не работает',
                    'work_position' => $user['WORK_POSITION'],
                    'work_phone' => $user['WORK_PHONE'],         
                ];

                $subordinateEmployees = CIntranetUtils::getSubordinateEmployees($userId, true, 'N', ['ID', 'NAME', 'LAST_NAME']);
                $employees = [];

                while($emp = $subordinateEmployees->Fetch()) {
                    $employeeId = intval($emp['ID']);

                    if($employeeId !== $userId) {
                        $employees[] = $employeeId;
                    }
                    
                }

                $arUsers[$userId]['subordinate_users_count'] = count($employees);
            
                foreach($user['UF_DEPARTMENT'] as $dep) {
                    if(intval($dep) > 0) {
                        $arUsers[$userId]['department'] = $dep;
                        $manager = $this->getUserManager($userId,$dep);
                        $arUsers[$userId]['manager'] = $this->getUserInfo($manager);
                    }
                }                
            
                $absense = UserAbsence::getCurrentMonth();
                
                foreach($absense as $id => $abs) {
                    if($id == $userId) {
                        foreach($abs as $offline) {
                            $ts = time();
                            if($ts  > $offline['DATE_FROM_TS'] && $ts < $offline['DATE_TO_TS']) {
                                $arUsers[$userId]['work_status'] = ($offline['ENTRY_TYPE'] === 'VACATION') ? 'в отпуске' : 'отсутствует ('. $offline['ENTRY_TYPE_VALUE'] .')';
                            }
                        }
                    }
                }

            }

        }

        return $arUsers;

    }

    public function setGridRows() {
        $users = $this->getUsers();
        $result = [];

        foreach($users as $id => $user) {
            $userLink = '/company/personal/user/'.$user['id'].'/';
            $result[] = [
                'data' => [
                    'ID' => $user['id'],
                    'FULL_NAME' => print_url($userLink, $user['name']),
                    'WORK_POSITION' => $user['work_position'],
                    'WORK_PHONE' => $user['work_phone'],
                    'MANAGER' => '<a href="/company/personal/user/'.$user['manager']['id'].'/">'.$user['manager']['name'].'</a>',
                    'SUBORDINATE_USERS_COUNT' => $user['subordinate_users_count'],
                    'WORK_STATUS' => $user['work_status']

                ]
            ];
        }

        return $result;
    }

    public function setGridOptions() {

        $gridOptions = new Options($this->userList);
        $sort = $gridOptions->getSorting([
            'sort' => ['ID' => 'DESC'],
            'vars' => [
                'by' => 'by',
                'order' => 'order'
            ]
        ]);
        $navParams = $gridOptions->GetNavParams();

        $nav = new PageNavigation($this->userList);

        $nav->AllowAllRecords(true)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        $this->arResult['GRID_OPTIONS'] = [
            'GRID_ID' => $this->userList,
            'COLUMNS' => $this->setGridColumns(),
            'ROWS' => $this->setGridRows(),
            'NAV_OBJECT' => $nav,
            'AJAX_MODE' => 'Y',
            'AJAX_OPTION_JUMP' => 'N',
            'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
            'PAGE_SIZES' => $this->setGridPageSizes(),
            'SHOW_CHECK_ALL_CHECKBOXES' => true,
            'SHOW_ROW_ACTIONS_MENU' => true,
            'SHOW_GRID_SETTINGS_MENU' => true,
            'SHOW_NAVIGATION_PANEL' => true,
            'SHOW_PAGINATION' => true,
            'SHOW_SELECTED_COUNTER' => true,
            'SHOW_TOTAL_COUNTER' => true,
            'SHOW_PAGESIZE' => true,
            'SHOW_ACTION_PANEL' => true,
            'ALLOW_COLUMNS_SORT' => true,
            'ALLOW_COLUMN_RESIZE' => true,
            'ALLOW_HORIZONTAL_SCROLL' => true,
            'ALLOW_SORT' => true,
            'ALLOW_PIN_HEADER' => true,
            'AJAX_OPTION_HISTORY' => 'N'
        ];

    }

    public function setRows() {
        $users = [
            '1' => [
                'id' => 1
            ]
        ];
        $result = [];

        foreach($users as $id => $user) {
            $result[] = [
                'data' => [
                    'id' => $id
                ]
            ];
        }

        return $this->getUsers();
    }

    public function executeComponent() {

        $this->includeModules();

        $this->arResult['GRID_ID'] = $this->userList;
        $this->setFilterOptions();
        $this->setGridOptions();

        $this->includeComponentTemplate();

    }



    public function getParentDepartmentId($department) {
        $arSection = CIBlockSection::GetByID($department)->fetch();
        if(is_array($arSection)) {
            return (int)$arSection['IBLOCK_SECTION_ID'];
        } else {
            throw new Exception("Result is empty");
            
        }
    }
    
    public function getDepartmentHead($departmentId) {
        $user = new User;
        $department = $user->getDepartmentHead($departmentId);
        return (int)$department;
    }
    
    public function getUserManager($userId, $departmentId) {
        $ufHead = $this->getDepartmentHead($departmentId);
        if($ufHead > 0 && ($ufHead !== $userId || $departmentId == 1)) {
            return $ufHead;
        } else {
            $parentDepartment = $this->getParentDepartmentId($departmentId);
            return $this->getUserManager($userId, $parentDepartment);
        }
    }

    public function getUserInfo($userId) {
        $user = UserTable::getById($userId)->fetch();
        return [
            'id' => $user['ID'],
            'name' => $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'],
        ];
    }

}