<?php
// --- PHP Setup ---
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/services/TimeTrackingService.php';
require_once __DIR__ . '/models/Project.php';
require_once __DIR__ . '/models/Task.php';
require_once __DIR__ . '/models/Employee.php';
require_once __DIR__ . '/models/TimeEntry.php';
require_once __DIR__ . '/../../plugins/function.php';

// Initialize service
$timeService = new TimeTrackingService($conn);


$viewingEmployeeId = decryptId($_GET['id']);
$viewingEmployee = $timeService->getEmployeeById($viewingEmployeeId);
if (!$viewingEmployee) {
    die("Error: Could not find employee to view (ID: {$viewingEmployeeId}).");
}
// Lấy danh sách mục nhập thời gian
$timeEntries = [];
$result = $conn->query("SELECT id, nhanvien_id, project_id, task_id, date, hours, description, is_overtime, created_at FROM time_entries");
while ($row = $result->fetch_assoc()) {
    $timeEntries[] = $row;
}
// Fetch data for dropdowns
$projects = [];
$result = $conn->query("SELECT id, name, ma_du_an FROM projects");
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

$tasks = [];
$result = $conn->query("SELECT id, name FROM tasks");
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

// Prepare data for FullCalendar
$calendarEvents = [];
$timeEntries = $timeService->getTimeEntries(null, null, $viewingEmployeeId, null, null);

// Khởi tạo các biến tổng hợp
$totalHours = 0;
$totalOvertimeHours = 0;
$daysWithEntries = [];

// Hàm kiểm tra xem một ngày có bị khóa không
function isDateLocked($date, $viewingEmployeeId, $conn)
{
    $formattedDate = $date->format('Y-m-d');
    $sql = "SELECT is_locked FROM time_entry_locks 
            WHERE employee_id = ? 
            AND lock_start_date <= ? 
            AND lock_end_date >= ?
            ORDER BY created_at DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iss", $viewingEmployeeId, $formattedDate, $formattedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $lock = $result->fetch_assoc();
        return $lock['is_locked'] == 1;
    }

    return false;
}

foreach ($timeEntries as $entry) {
    // Kiểm tra xem ngày này có bị khóa không
    $isLocked = isDateLocked($entry->Date, $viewingEmployeeId, $conn);

    $calendarEvents[] = [
        'id' => $entry->Id,
        'title' => $entry->ProjectCode . '-' . $entry->HoursWorked . 'h', // Loại bỏ chữ "OVT" hoặc "Regular"
        'start' => $entry->Date->format('Y-m-d'),
        'classNames' => $entry->IsOvertime ? 'event-ovt' : '', // Thêm lớp CSS nếu là OVT
        'extendedProps' => [
            // 'projectId' => $entry->ProjectId,
            'projectcode' => $entry->ProjectCode,
            'taskId' => $entry->TaskId,
            'hours' => $entry->HoursWorked,
            'isOvertime' => $entry->IsOvertime,
            'isLocked' => $isLocked // Thêm trạng thái khóa
        ]
    ];

    // Tổng hợp giờ công
    $totalHours += $entry->HoursWorked;
    if ($entry->IsOvertime) {
        $totalOvertimeHours += $entry->HoursWorked;
    }

    // Lưu lại các ngày có giờ công
    $daysWithEntries[$entry->Date->format('Y-m-d')] = true;
}

// Tính tổng số ngày có giờ công
$totalDaysWithEntries = count($daysWithEntries);

// Tính trung bình giờ/ngày
$averageHoursPerDay = $totalDaysWithEntries > 0 ? round($totalHours / $totalDaysWithEntries, 2) : 0;
function consolidateEvents($timeEntries)
{
    $consolidated = [];
    $lockedDates = []; // Theo dõi các ngày bị khóa

    global $conn, $viewingEmployeeId;

    foreach ($timeEntries as $entry) {
        $key = $entry->Date->format('Y-m-d') . '_' . $entry->ProjectId;
        $dateStr = $entry->Date->format('Y-m-d');

        // Kiểm tra và lưu trạng thái khóa cho ngày này
        if (!isset($lockedDates[$dateStr])) {
            $lockedDates[$dateStr] = isDateLocked($entry->Date, $viewingEmployeeId, $conn);
        }
        $isLocked = $lockedDates[$dateStr];

        if (!isset($consolidated[$key])) {
            $consolidated[$key] = [
                'id' => $entry->Id,
                'title' => $entry->ProjectCode,
                'start' => $dateStr,
                'extendedProps' => [
                    'projectId' => $entry->ProjectId,
                    'projectcode' => $entry->ProjectCode,
                    'regularHours' => 0,
                    'overtimeHours' => 0,
                    'isLocked' => $isLocked // Thêm trạng thái khóa
                ]
            ];
        }

        if ($entry->IsOvertime) {
            $consolidated[$key]['extendedProps']['overtimeHours'] += $entry->HoursWorked;
        } else {
            $consolidated[$key]['extendedProps']['regularHours'] += $entry->HoursWorked;
        }
    }

    return array_values($consolidated);
}
$calendarEvents = consolidateEvents($timeEntries);
$calendarEventsJson = json_encode($calendarEvents);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON encoding error: ' . json_last_error_msg());
}
?>

<!-- <body class="hold-transition sidebar-mini"> -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Man-Hour Calendar</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Calendar View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Bảng tổng hợp (1/3 chiều ngang) -->
                <div class="col-md-5">
                    <div class="card card-primary  border-0 shadow-sm">
                        <div class="card-header border-bottom">
                            <span>
                                <h5 class="card-title">Tổng hợp giờ công -&nbsp;</h5>
                                <h5 class="card-title" id="current-month"><?= date('M/Y') ?></h5>
                            </span>

                        </div>
                        <div class="card-body">
                            <div class="row row-cols-1 row-cols-sm-2 g-3">
                                <div class="col p-2">
                                    <div class="card h-100 border-start border-info border-4">
                                        <div class="card-body p-3">
                                            <h6 class="text-muted mb-1">Tổng giờ trong tháng</h6>
                                            <div class="fs-5 fw-semibold" id="total-month-hours"><?= $totalHours ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col p-2">
                                    <div class="card h-100 border-start border-success border-4" style="background-color:#f4f4f4;">
                                        <div class="card-body p-3">
                                            <h6 class="text-muted mb-1">Giờ làm thường</h6>
                                            <div class="fs-5 fw-semibold" id="regular-month-hours"><?= $totalHours - $totalOvertimeHours ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col p-2">
                                    <div class="card h-100 border-start border-warning border-4" style="background-color:#f4f4f4;">
                                        <div class="card-body p-3">
                                            <h6 class="text-muted mb-1">Giờ làm thêm</h6>
                                            <div class="fs-5 fw-semibold" id="overtime-month-hours"><?= $totalOvertimeHours ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col p-2">
                                    <div class="card h-100 border-start border-danger border-4">
                                        <div class="card-body p-3">
                                            <h6 class="text-muted mb-1">Trung bình giờ/ngày</h6>
                                            <div class="fs-5 fw-semibold" id="average-day-hours"><?= $averageHoursPerDay ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h5 class="card-title">Tổng hợp giờ công theo dự án</h5>
                        </div>
                        <div class="card-body">
                            <table id="project-summary-table" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Dự án</th>
                                        <th>Giờ thường</th>
                                        <th>Giờ OVT</th>
                                        <th>Tổng giờ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dữ liệu sẽ được cập nhật bằng JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Lịch (2/3 chiều ngang) -->
                <div class="col-md-7">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <div class="legend-color legend-public"></div>
                                    <span>Ngày nghỉ lễ</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color legend-company"></div>
                                    <span>Ngày nghỉ công ty</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="pages/v-time/add_new_mhrs.js"></script>

<style>
    #project-search-results {
        max-height: 300px;
        overflow-y: auto;
        z-index: 1060;
        /* Higher than modal's z-index */
    }

    #project-search-results .dropdown-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
    }

    #project-search-results .dropdown-item:hover {
        background-color: #e9ecef;
    }

    .project-search-container {
        position: relative;
    }

    /* Styling for locked events */
    .event-locked {
        opacity: 0.8;
        cursor: not-allowed !important;
        border-style: dashed !important;
        position: relative;
    }

    .event-locked:before {
        content: "🔒";
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 10px;
    }

    /* Additional calendar date indicator for locked dates */
    .locked-day {
        position: relative;
    }

    .locked-day:after {
        content: '🔒';
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 10px;
        color: #dc3545;
    }

    /* Holiday styling */
    .holiday-public {
        background-color: rgba(244, 0, 0, 0.6) !important;
        border: none !important;
    }

    .holiday-company {
        background-color: rgba(0, 94, 255, 0.6) !important;
        border: none !important;
    }

    .fc-day.holiday-public::after,
    .fc-day.holiday-company::after {
        content: attr(data-holiday-name);
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        font-size: 0.8em;
        padding: 2px;
        text-align: center;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .fc-day.holiday-public::after {
        background-color: rgba(244, 0, 0, 0.6);
        ;
        color: #d63031;
    }

    .fc-day.holiday-company::after {
        background-color: rgba(0, 94, 255, 0.6);
        color: #0984e3;
    }

    /* Legend for holidays */
    .calendar-legend {
        display: flex;
        gap: 15px;
        padding: 10px;
        margin-bottom: 10px;
        font-size: 0.9em;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .legend-color {
        width: 15px;
        height: 15px;
        border-radius: 3px;
    }

    .legend-public {
        background-color: rgba(244, 0, 0, 0.6);
        border: 1px solid rgba(255, 150, 150, 0.5);
    }

    .legend-company {
        background-color: rgba(0, 94, 255, 0.6);
        border: 1px solid rgba(150, 170, 255, 0.5);
    }
</style>

<script>
    // Add this code to the beginning of your script section to define the Toast object
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    var calendar; // Khai báo biến toàn cục
    var dailyRegularHours = {}; // Khai báo biến toàn cục để lưu trữ giờ công hàng ngày
    $(function() {
        // Add custom styling for today's cell
        const styleElement = document.createElement('style');
        styleElement.textContent = `
            .fc .fc-day-today {
                background-color: #fff7d9 !important;
            }
            
            /* Styling for days with >8 regular hours */
            .overwork-day {
                background-color:rgb(252, 195, 195) !important;
            }       
            /* Stronger highlighting for mobile */
            @media (max-width: 768px) {
                .fc .fc-day-today {
                    background-color: #fff7d9 !important;
                }
                
                .overwork-day {
                    background-color: #ff6fa5 !important;
                }
            }
        `;
        document.head.appendChild(styleElement);

        // Create a map to track regular hours by date      

        var calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hôm nay',
                month: 'Tháng',
                week: 'Tuần',
                day: 'Ngày'
            },
            selectable: true,
            editable: true,
            // Mobile optimization settings
            longPressDelay: 250, // Shorter delay for better mobile experience
            eventLongPressDelay: 250,
            selectLongPressDelay: 250,
            // Add single tap for date selection on mobile
            dateClick: function(info) {
                if (isMobileDevice()) {
                    openManhourForm(info.dateStr);
                    refreshCalendarData();
                }
            },
            // events: {
            //     url: 'pages/v-time/api/get_events.php',
            //     method: 'GET',
            //     extraParams: function() {
            //         return {
            //             nhanvien_id: <?= $viewingEmployeeId ?>
            //         };
            //     },
            //     failure: function() {
            //         Toast.fire({
            //             icon: 'error',
            //             title: 'Không thể tải dữ liệu lịch'
            //         });
            //     },
            //     success: function(events) {
            //         // Clear previous data
            //         Object.keys(dailyRegularHours).forEach(key => delete dailyRegularHours[key]);

            //         // Process events to calculate regular hours per day
            //         events.forEach(event => {
            //             // Make sure isLocked exists and is a boolean
            //             if (typeof event.extendedProps.isLocked === 'undefined') {
            //                 console.warn('Event missing isLocked property, defaulting to not locked', event);
            //                 event.extendedProps.isLocked = false;
            //             }
            //             // Ensure date format is consistent - convert to YYYY-MM-DD
            //             const dateStr = event.start.split('T')[0];
            //             const regularHours = parseFloat(event.extendedProps.regularHours) || 0;

            //             if (!dailyRegularHours[dateStr]) {
            //                 dailyRegularHours[dateStr] = 0;
            //             }

            //             dailyRegularHours[dateStr] += regularHours;
            //         });

            //         // Add classes for each day cell that has more than 8 regular hours
            //         $('.fc-daygrid-day').each(function() {
            //             const dateAttr = $(this).data('date');
            //             if (dailyRegularHours[dateAttr] && dailyRegularHours[dateAttr] > 8) {
            //                 $(this).addClass('overwork-day');
            //             } else {
            //                 $(this).removeClass('overwork-day');
            //             }
            //         });

            //         // console.log('Daily regular hours:', dailyRegularHours);
            //     }
            // },
            events: function(fetchInfo, successCallback, failureCallback) {
                // Gọi song song API giờ công và API ngày nghỉ
                Promise.all([
                        $.ajax({
                            url: 'pages/v-time/api/get_events.php',
                            method: 'GET',
                            data: {
                                nhanvien_id: <?= $viewingEmployeeId ?>
                            }
                        }),
                        $.ajax({
                            url: 'pages/v-holiday/api/get_holidays.php',
                            method: 'GET'
                        })
                    ])
                    .then(function([eventsResponse, holidaysResponse]) {
                        // Xử lý giờ công như cũ
                        const events = eventsResponse;

                        // Xử lý holidays nếu có
                        if (holidaysResponse.success && holidaysResponse.data) {
                            holidaysResponse.data.forEach(holiday => {
                                events.push(holiday); // holiday đã là dạng background event
                            });
                        }

                        // Cập nhật biến tổng hợp giờ công
                        Object.keys(dailyRegularHours).forEach(key => delete dailyRegularHours[key]);
                        events.forEach(event => {
                            if (event.display !== 'background') {
                                const dateStr = event.start.split('T')[0];
                                const regularHours = parseFloat(event.extendedProps?.regularHours || 0);
                                if (!dailyRegularHours[dateStr]) {
                                    dailyRegularHours[dateStr] = 0;
                                }
                                dailyRegularHours[dateStr] += regularHours;
                            }
                        });

                        successCallback(events);
                    })
                    .catch(function(error) {
                        console.error(error);
                        Toast.fire({
                            icon: 'error',
                            title: 'Không thể tải dữ liệu lịch'
                        });
                        failureCallback(error);
                    });
            },

            dayCellDidMount: function(info) {
                // Format date the same way as in dailyRegularHours
                const dateStr = info.date.toISOString().split('T')[0];
                if (dailyRegularHours[dateStr] && dailyRegularHours[dateStr] > 8) {
                    info.el.classList.add('overwork-day');
                }
            },
            eventContent: function(arg) {
                const event = arg.event;
                const regularHours = event.extendedProps.regularHours || 0;
                const overtimeHours = event.extendedProps.overtimeHours || 0;
                const isLocked = event.extendedProps.isLocked || false;
                const project = event.extendedProps?.project || '';


                // Xác định class dựa trên loại giờ
                let eventClass = 'event-regular';
                if (regularHours > 0 && overtimeHours > 0) {
                    eventClass = 'event-mixed';
                } else if (overtimeHours > 0) {
                    eventClass = 'event-ovt';
                }

                // Thêm class nếu bị khóa
                if (isLocked) {
                    eventClass += ' event-locked';
                }

                // Thêm class vào element
                if (arg.el) {
                    arg.el.classList.add(eventClass);
                }

                // return {
                //     html: `
                //         <div class="fc-event-main-content ${eventClass}" style="display: flex; align-items: center; gap: 4px;">
                //             ${isLocked ? '<i class="fas fa-lock text-warning mr-1" title="Đã khóa"></i>' : ''}
                //             <span class="project-code">${event.extendedProps.projectcode}</span>
                //             <span class="hours d-flex">
                //                 ${regularHours > 0 ? `<span class="hours-regular">${regularHours}h</span>` : ''}
                //                 ${overtimeHours > 0 ? `<span class="hours-overtime">+${overtimeHours}h</span>` : ''}
                //             </span>
                //         </div>
                //     `
                // };
                return {
                    html: `
                            <div class="fc-event-main-content ${eventClass}" style="display: flex; align-items: center; gap: 4px;">
                                ${isLocked ? '<i class="fas fa-lock text-warning mr-1" title="Đã khóa"></i>' : ''}
                                ${event.extendedProps?.projectcode ? `<span class="project-code">${event.extendedProps.projectcode}</span>` : ''}
                                <span class="hours d-flex">
                                    ${regularHours > 0 ? `<span class="hours-regular">${regularHours}h</span>` : ''}
                                    ${overtimeHours > 0 ? `<span class="hours-overtime">+${overtimeHours}h</span>` : ''}
                                </span>
                            </div>
                        `
                };

            },
            eventDidMount: function(info) {
                // Move the event mount handling here
                debugLockStatus(); // If you want to keep debug logging
            },

            // Rest of your calendar options remain the same
            datesSet: function(info) {
                // Gọi hàm cập nhật bảng tổng hợp khi chuyển tháng
                updateProjectSummary(info.start, info.end);
            },
            select: function(info) {
                openManhourForm(info.startStr);
                refreshCalendarData();
            },
            eventClick: function(info) {
                // Kiểm tra nếu sự kiện bị khóa
                if (info.event.extendedProps.isLocked) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Giờ công đã bị khóa',
                        text: 'Giờ công này đã bị khóa và không thể chỉnh sửa. Vui lòng liên hệ quản lý nếu cần thay đổi.',
                        confirmButtonColor: '#3085d6'
                    });
                    return; // Dừng xử lý, không cho phép chỉnh sửa
                }

                // Tiếp tục với chức năng chỉnh sửa nếu không bị khóa
                editManhourForm(info.event);
                refreshCalendarData();
            },
            eventDrop: function(info) {
                // Kiểm tra xem sự kiện có bị khóa không
                const sourceIsLocked = checkIfDateIsLocked(info.oldEvent.startStr);
                const targetIsLocked = checkIfDateIsLocked(info.event.startStr);

                if (sourceIsLocked || targetIsLocked) {
                    info.revert(); // Revert the move

                    let message = 'Cannot move time entry:';
                    if (sourceIsLocked) message += ' Source date is locked.';
                    if (targetIsLocked) message += ' Target date is locked.';
                    Swal.fire({
                        icon: 'warning',
                        title: 'Giờ công đã bị khóa',
                        text: 'Giờ công này đã bị khóa và không thể di chuyển. Vui lòng liên hệ quản lý nếu cần thay đổi.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                // Tiếp tục xử lý kéo thả nếu không bị khóa
                // Show confirmation dialog
                Swal.fire({
                    title: 'Xác nhận thay đổi',
                    text: `Bạn có muốn di chuyển giờ công sang ngày ${info.event.startStr}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Đồng ý',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Đang cập nhật...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // First get the event details to get all entry IDs
                        $.ajax({
                            url: 'pages/v-time/api/get_event_details.php',
                            method: 'GET',
                            data: {
                                date: info.oldEvent.startStr,
                                project_id: info.event.extendedProps.projectId,
                                nhanvien_id: <?= $viewingEmployeeId ?>
                            },
                            success: function(response) {
                                if (response && response.success && response.tasks) {
                                    // Extract all entry IDs from tasks
                                    const entryIds = response.tasks.map(task => task.id);

                                    // Send update request
                                    $.ajax({
                                        url: 'pages/v-time/api/update_event_date.php',
                                        method: 'POST',
                                        data: {
                                            old_date: info.oldEvent.startStr,
                                            new_date: info.event.startStr,
                                            project_id: info.event.extendedProps.projectId,
                                            nhanvien_id: <?= $viewingEmployeeId ?>,
                                            entry_ids: JSON.stringify(entryIds)
                                        },
                                        success: function(updateResponse) {
                                            Swal.close();
                                            if (updateResponse.success) {
                                                Toast.fire({
                                                    icon: 'success',
                                                    title: 'Đã cập nhật ngày thành công'
                                                });
                                                calendar.refetchEvents();
                                                updateProjectSummary(calendar.view.currentStart, calendar.view.currentEnd);
                                            } else {
                                                info.revert();
                                                Toast.fire({
                                                    icon: 'error',
                                                    title: updateResponse.message || 'Không thể cập nhật ngày'
                                                });
                                            }
                                        },
                                        error: function(xhr) {
                                            Swal.close();
                                            info.revert();
                                            Toast.fire({
                                                icon: 'error',
                                                title: 'Lỗi khi cập nhật ngày: ' + xhr.statusText
                                            });
                                        }
                                    });
                                } else {
                                    Swal.close();
                                    info.revert();
                                    Toast.fire({
                                        icon: 'error',
                                        title: 'Không tìm thấy dữ liệu giờ công'
                                    });
                                }
                            },
                            error: function(xhr) {
                                Swal.close();
                                info.revert();
                                Toast.fire({
                                    icon: 'error',
                                    title: 'Lỗi khi tải dữ liệu giờ công: ' + xhr.statusText
                                });
                            }
                        });
                    } else {
                        info.revert();
                    }
                });
            },
        });
        calendar.render();
    });

    // Helper function to detect mobile devices
    function isMobileDevice() {
        return (window.innerWidth <= 768 ||
            navigator.maxTouchPoints > 0 ||
            /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent));
    }

    function refreshCalendarData() {
        //console.log("Đang tải lại dữ liệu lịch...");
        calendar.refetchEvents();
        updateProjectSummary(calendar.view.currentStart, calendar.view.currentEnd);

        // Reapply overwork-day class to days after events refresh
        setTimeout(() => {
            $('.fc-daygrid-day').each(function() {
                const dateAttr = $(this).data('date');
                if (dailyRegularHours[dateAttr] && dailyRegularHours[dateAttr] > 8) {
                    $(this).addClass('overwork-day');
                } else {
                    $(this).removeClass('overwork-day');
                }
            });
        }, 500);
    }

    function formatDateLocal(date) {
        return date.toLocaleDateString('en-CA'); // chuẩn YYYY-MM-DD
    }

    function updateProjectSummary(viewStart, viewEnd) {
        const nhanvienId = <?= $viewingEmployeeId ?>;

        // Get current month from calendar view
        const currentDate = new Date(viewStart);
        currentDate.setDate(currentDate.getDate() + 15);
        const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);

        // Format dates to YYYY-MM-DD
        const startDate = formatDateLocal(firstDayOfMonth);
        const endDate = formatDateLocal(lastDayOfMonth);

        const tbody = $('#project-summary-table tbody');

        // Update month display
        $('#current-month').text(firstDayOfMonth.toLocaleDateString('vi-VN', {
            month: '2-digit',
            year: 'numeric'
        }));

        $.post('pages/v-time/api/get_project_summary.php', {
            nhanvien_id: nhanvienId,
            start_date: startDate,
            end_date: endDate
        }, function(response) {
            try {
                tbody.empty();

                const res = typeof response === 'object' ? response : JSON.parse(response);

                if (res.success) {
                    // Update monthly statistics boxes
                    if (res.statistics) {
                        $('#total-month-hours').text(res.statistics.totalHours || 0);
                        $('#regular-month-hours').text(res.statistics.regularHours || 0);
                        $('#overtime-month-hours').text(res.statistics.overtimeHours || 0);
                        $('#average-day-hours').text(res.statistics.averageHours || 0);
                    }

                    // Update project summary table
                    res.data.forEach(row => {
                        tbody.append(`
                            <tr>
                                <td>${row.project_code}</td>
                                <td style="text-align:right;">${row.month_reg_hours}</td>
                                <td style="text-align:right;">${row.month_ovt_hours}</td>
                                <td style="text-align:right;">${row.total_hours}</td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.append(`
                        <tr>
                            <td colspan="4" class="text-center">Không có dữ liệu trong tháng này</td>
                        </tr>
                    `);
                }
            } catch (e) {
                //console.error('JSON parse error:', e);
                tbody.append(`
                    <tr>
                        <td colspan="4" class="text-center text-danger">Lỗi khi tải dữ liệu</td>
                    </tr>
                `);
            }
        }).fail(function(xhr, status, error) {
            //console.error('Lỗi khi gửi yêu cầu:', error);
            tbody.append(`
                <tr>
                    <td colspan="4" class="text-center text-danger">Không thể kết nối đến server</td>
                </tr>
            `);
        });
    }

    // Hàm mở form thêm giờ công
    function openManhourForm(date) {
        // Kiểm tra xem ngày này có bị khóa không
        const isDateLocked = checkIfDateIsLocked(date);

        // Lấy danh sách giờ công của ngày
        const events = calendar.getEvents().filter(event => event.startStr === date);

        // Aggregate hours by project
        const projectSummary = {};

        events.forEach(event => {
            const projectId = event.extendedProps.projectId;
            const projectCode = event.extendedProps.projectcode;
            const regularHours = event.extendedProps.regularHours || 0;
            const overtimeHours = event.extendedProps.overtimeHours || 0;

            if (!projectSummary[projectId]) {
                projectSummary[projectId] = {
                    projectId: projectId,
                    projectCode: projectCode,
                    regularHours: 0,
                    overtimeHours: 0
                };
            }

            projectSummary[projectId].regularHours += parseFloat(regularHours);
            projectSummary[projectId].overtimeHours += parseFloat(overtimeHours);
        });

        // Format date for display
        const formattedDate = new Date(date).toLocaleDateString('vi-VN', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Create the project summary table
        let summaryTableHtml = '';
        const projectEntries = Object.values(projectSummary);
        const totalRegular = projectEntries.reduce((sum, p) => sum + p.regularHours, 0);
        const totalOvertime = projectEntries.reduce((sum, p) => sum + p.overtimeHours, 0);

        if ((projectEntries.length > 0) && (totalRegular + totalOvertime > 0)) {
            summaryTableHtml = `
            <div class="mb-4">
                <h5>Tóm tắt giờ công cho ngày ${formattedDate}</h5>
                <table class="table table-bordered table-striped">
                    <thead class="thead-light">
                        <tr>
                            <th>Dự án</th>
                            <th>Giờ thường</th>
                            <th>Giờ OVT</th>
                            <th>Tổng giờ</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${projectEntries.map(entry => `
                            <tr>
                                <td>${entry.projectCode}</td>
                                <td>${entry.regularHours}</td>
                                <td>${entry.overtimeHours}</td>
                                <td><strong>${(entry.regularHours + entry.overtimeHours).toFixed(1)}</strong></td>
                            </tr>
                        `).join('')}
                        <tr class="table-secondary">
                            <td><strong>Tổng cộng</strong></td>
                            <td><strong>${totalRegular.toFixed(1)}</strong></td>
                            <td><strong>${totalOvertime.toFixed(1)}</strong></td>
                            <td><strong>${(totalRegular + totalOvertime).toFixed(1)}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
        } else {
            summaryTableHtml = `
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle mr-2"></i> 
                Chưa có dữ liệu giờ công cho ngày ${formattedDate}
            </div>
        `;
        }

        // Show the modal with project summary and action buttons
        // Hiển thị cảnh báo nếu ngày bị khóa
        const lockWarning = isDateLocked ? `
            <div class="alert alert-warning">
                <i class="fas fa-lock mr-2"></i>
                <strong>Ngày này đã bị khóa.</strong> Không thể thêm hoặc chỉnh sửa giờ công.
            </div>
        ` : '';

        Swal.fire({
            title: `Quản lý giờ công`,
            html: `
            ${lockWarning}
            ${summaryTableHtml}
            <div class="text-center">
                ${!isDateLocked ? `
                    <button id="addNewManhour" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Thêm giờ công mới
                    </button>
                    ${events.length > 0 ? `
                        <button id="editAllManhour" class="btn btn-info ml-2">
                            <i class="fas fa-edit mr-1"></i> Chỉnh sửa tất cả
                        </button>
                    ` : ''}
                ` : ''}
            </div>
            `,
            width: 800,
            showCancelButton: true,
            cancelButtonText: 'Đóng',
            showConfirmButton: false,
            didOpen: () => {
                const addButton = document.getElementById('addNewManhour');
                if (addButton) {
                    addButton.addEventListener('click', () => {
                        Swal.close();
                        addNewManhour(date);
                    });
                }

                const editButton = document.getElementById('editAllManhour');
                if (editButton) {
                    editButton.addEventListener('click', () => {
                        Swal.close();
                        // Get first event to edit (this will load all entries for this date)
                        editManhourForm(events[0]);
                    });
                }
            }
        });
    }

    function saveEvent(date, entry) {
        //console.log("Saving event with data:", entry);

        // Create a complete data object with all required fields
        const eventData = {
            date: date,
            project_id: entry.project,
            task_id: entry.task,
            regular_hours: entry.isOvertime ? 0 : entry.hours,
            overtime_hours: entry.isOvertime ? entry.hours : 0,
            nhanvien_id: <?= $viewingEmployeeId ?>,
            tasks: JSON.stringify([{ // Server expects tasks array
                id: null,
                task_id: entry.task,
                regular_hours: entry.isOvertime ? 0 : entry.hours,
                overtime_hours: entry.isOvertime ? entry.hours : 0
            }])
        };

        //console.log("Sending data to server:", eventData);

        return new Promise((resolve, reject) => {
            $.ajax({
                url: 'pages/v-time/update_event.php',
                method: 'POST',
                data: eventData,
                dataType: 'json',
                success: function(response) {
                    //console.log("Server response:", response);
                    if (response && response.success) {
                        resolve({
                            id: response.id,
                            projectCode: response.project_code,
                            regularHours: eventData.regular_hours,
                            overtimeHours: eventData.overtime_hours
                        });
                        // window.location.reload();
                    } else {
                        const errorMsg = response && response.message ? response.message : 'Unknown error';
                        //console.error('Save event error:', errorMsg);
                        reject(new Error(errorMsg));
                    }
                },
                error: function(xhr) {
                    //console.error('Server error:', xhr.responseText);
                    reject(new Error('Server error: ' + (xhr.responseText || 'Unknown error')));
                }
            });
        });
    }


    function updateEvent(event, formData) {
        // console.log("🔍 UPDATE EVENT - Starting with inputs:", {
        //     eventId: event.id,
        //     date: event.startStr,
        //     formData: formData
        // });

        // Calculate total regular and overtime hours from tasks array
        let totalRegularHours = 0;
        let totalOvertimeHours = 0;

        if (formData.tasks && formData.tasks.length > 0) {
            formData.tasks.forEach(task => {
                totalRegularHours += parseFloat(task.regular_hours) || 0;
                totalOvertimeHours += parseFloat(task.overtime_hours) || 0;
            });
            // console.log("📊 Calculated totals from tasks:", {
            //     totalRegularHours,
            //     totalOvertimeHours
            // });
        }

        const eventData = {
            id: event.id,
            date: formData.date || event.startStr,
            nhanvien_id: <?= $viewingEmployeeId ?>,
            project_id: formData.project,
            original_project_id: formData.originalProjectId // Send the original project ID for tracking changes
        };

        // Add the deleted task entries to the eventData
        if (formData.deletedTaskEntries && formData.deletedTaskEntries.length > 0) {
            eventData.deleted_task_entries = JSON.stringify(formData.deletedTaskEntries);
            // console.log("⚠️ Tasks to delete:", formData.deletedTaskEntries);
        }

        // For PHP to properly parse the tasks array
        if (formData.tasks) {
            // console.log("📝 Processing tasks array:", formData.tasks);
            // Convert tasks array to properly named form fields for PHP
            formData.tasks.forEach((task, i) => {
                eventData[`tasks[${i}][task_id]`] = task.task_id;
                eventData[`tasks[${i}][regular_hours]`] = parseFloat(task.regular_hours) || 0;
                eventData[`tasks[${i}][overtime_hours]`] = parseFloat(task.overtime_hours) || 0;

                // Make sure entry_ids are properly handled
                if (task.entry_ids && task.entry_ids.length) {
                    eventData[`tasks[${i}][entry_ids]`] = JSON.stringify(task.entry_ids);
                } else {
                    eventData[`tasks[${i}][entry_ids]`] = JSON.stringify([]);
                }
            });
        } else {
            eventData.task_id = formData.task_id || formData.task;
            eventData.regular_hours = parseFloat(formData.regularHours || 0);
            eventData.overtime_hours = parseFloat(formData.overtimeHours || 0);
        }

        // console.log("📤 Final data being sent to server:", eventData);

        $.ajax({
            url: 'pages/v-time/update_event.php',
            method: 'POST',
            data: eventData,
            dataType: 'json',
            success: function(response) {
                // console.log("📥 Server response:", response);

                if (response && response.success) {
                    // Use calculated totals if we have tasks array
                    const regularHours = formData.tasks ? totalRegularHours :
                        (response.regular_hours || eventData.regular_hours || 0);
                    const overtimeHours = formData.tasks ? totalOvertimeHours :
                        (response.overtime_hours || eventData.overtime_hours || 0);

                    // console.log("✅ Hours after processing response:", {
                    //     regularHours,
                    //     overtimeHours
                    // });

                    // Update the calendar event
                    const updatedEvent = {
                        id: event.id,
                        title: response.project_code || event.title,
                        extendedProps: {
                            projectId: formData.project,
                            projectcode: response.project_code || event.extendedProps.projectcode,
                            regularHours: regularHours,
                            overtimeHours: overtimeHours
                        }
                    };

                    calendar.getEventById(event.id).remove();
                    calendar.addEvent(updatedEvent);

                    Toast.fire({
                        icon: 'success',
                        title: `Đã cập nhật giờ công: ${regularHours}h thường + ${overtimeHours}h OVT`
                    });
                    calendar.refetchEvents();
                    updateProjectSummary(calendar.view.currentStart, calendar.view.currentEnd);
                } else {
                    const errorMsg = response && response.message ? response.message : 'Unknown error';
                    console.error('❌ Update event error:', errorMsg);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error updating event',
                        text: errorMsg
                    });
                }
            },
            error: function(xhr) {
                console.error('❌ Server error:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error updating event',
                    text: 'Server error: ' + (xhr.responseText || 'Unknown error')
                });
            }
        });
    }

    function renderEventContent(eventInfo) {
        const event = eventInfo.event;
        const projectCode = event.extendedProps.projectcode;
        const taskName = event.extendedProps.taskName;
        const regularHours = event.extendedProps.regularHours || 0;
        const overtimeHours = event.extendedProps.overtimeHours || 0;

        return {
            html: `
            <div class="project-task-group">
                <div class="project-code">${projectCode}</div>
                <div class="task-name">${taskName}</div>
                <div class="hours-container">
                    ${regularHours > 0 ? `<span class="hours-regular">${regularHours}h</span>` : ''}
                    ${overtimeHours > 0 ? `<span class="hours-overtime">${overtimeHours}h OVT</span>` : ''}
                </div>
            </div>
        `
        };
    }

    function editManhourForm(event) {
        // Kiểm tra xem sự kiện có bị khóa không
        if (event.extendedProps.isLocked) {
            Swal.fire({
                icon: 'warning',
                title: 'Giờ công đã bị khóa',
                text: 'Giờ công này đã bị khóa và không thể chỉnh sửa. Vui lòng liên hệ quản lý nếu cần thay đổi.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        // Tiếp tục với mã hiện tại nếu không bị khóa
        const regularHours = event.extendedProps.regularHours || 0;
        const overtimeHours = event.extendedProps.overtimeHours || 0;
        const projectId = event.extendedProps.projectId;
        const eventId = event.id;
        const eventDate = event.startStr;

        // First, fetch the detailed task information for this event
        $.ajax({
            url: 'pages/v-time/api/get_event_details.php',
            method: 'GET',
            data: {
                event_id: eventId,
                date: eventDate,
                project_id: projectId,
                nhanvien_id: <?= $viewingEmployeeId ?>
            },

            success: function(response) {
                try {
                    const data = typeof response === 'object' ? response : JSON.parse(response);

                    if (data.success) {
                        const rawTasks = data.tasks || [];

                        // Gộp các task trùng task_id
                        const groupedTasks = {};
                        rawTasks.forEach(task => {
                            const taskId = task.task_id;
                            if (!groupedTasks[taskId]) {
                                groupedTasks[taskId] = {
                                    task_id: taskId,
                                    task_name: task.task_name,
                                    regular_hours: parseFloat(task.regular_hours) || 0,
                                    overtime_hours: parseFloat(task.overtime_hours) || 0,
                                    entry_ids: [task.id]
                                };
                            } else {
                                groupedTasks[taskId].regular_hours += parseFloat(task.regular_hours) || 0;
                                groupedTasks[taskId].overtime_hours += parseFloat(task.overtime_hours) || 0;
                                groupedTasks[taskId].entry_ids.push(task.id);
                            }
                        });

                        // Tính tổng giờ
                        let totalRegular = 0,
                            totalOvertime = 0;

                        const tasksHtml = `
<div class="table-responsive mb-3">
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Công việc</th>
                <th>Giờ thường</th>
                <th>Giờ OVT</th>
                <th>...</th>
            </tr>
        </thead>
        <tbody id="tasks-table-body">
            ${Object.values(groupedTasks).map(task => {
                totalRegular += task.regular_hours;
                totalOvertime += task.overtime_hours;
                // Use JSON.stringify for the entry_ids to ensure they're properly encoded
                const entryIdsAttr = JSON.stringify(task.entry_ids);
                return `
                <tr data-task-id="${task.task_id}" data-entry-ids='${entryIdsAttr}'>
                    <td class="task-name">${task.task_name}
                        <button type="button" class="btn btn-outline-primary btn-sm edit-task ml-2" title="Sửa đổi công việc">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <td><input type="number" class="form-control form-control-sm task-regular-hours" 
                            value="${task.regular_hours}" min="0" step="0.5"></td>
                    <td><input type="number" class="form-control form-control-sm task-overtime-hours" 
                            value="${task.overtime_hours}" min="0" step="0.5"></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-task">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('')}
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th id="total-regular-hours">${totalRegular}</th>
                <th id="total-overtime-hours">${totalOvertime}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
<button type="button" id="add-task-btn" class="btn btn-primary btn-sm mb-3">
    <i class="fas fa-plus"></i> Add Task
</button>`;

                        showEditModal(event, tasksHtml, data);
                    } else {
                        showEditModal(event, '', {
                            project: {
                                id: projectId
                            }
                        });
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showEditModal(event, '', {
                        project: {
                            id: projectId
                        }
                    });
                }
            },

            error: function() {
                console.error('Failed to fetch task details');
                showEditModal(event, '', {
                    project: {
                        id: projectId
                    }
                });
            }
        });
    }

    function showEditModal(event, tasksHtml, data) {
        const regularHours = event.extendedProps.regularHours || 0;
        const overtimeHours = event.extendedProps.overtimeHours || 0;

        // Create a variable to track deleted task entries
        const deletedTaskEntries = [];

        // Replace the project select HTML with input search
        const projectSelectHtml = `
        <div class="form-group mb-3">
            <label for="project">Dự án:</label>
            <div class="input-group">
                <input type="text" 
                    id="project-search" 
                    class="form-control" 
                    value="${event.extendedProps.projectcode || ''} - ${data.project?.name || ''}"
                    placeholder="Tìm kiếm dự án..."
                    readonly>
                <input type="hidden" 
                    id="project" 
                    value="${event.extendedProps.projectId || ''}">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" id="search-project-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div id="project-search-results" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;">
            </div>
        </div>
    `;

        Swal.fire({
            title: 'Cập nhật giờ công',
            width: 800,
            html: `
            <div style="text-align: left;">           
                ${projectSelectHtml}
                ${tasksHtml || `
                <div class="form-group mb-3">
                    <label for="task">Công việc:</label>
                    <select id="task" class="form-control">
                        <option value="">Chọn công việc</option>
                        <?php foreach ($tasks as $task): ?>
                            <option value="<?= $task['id'] ?>" 
                                data-project-id="<?= $task['project_id'] ?>" 
                                ${event.extendedProps.taskId == <?= $task['id'] ?> ? 'selected' : ''}>
                                <?= $task['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="regularHours">Giờ thường:</label>
                            <input id="regularHours" type="number" min="0" step="0.5" 
                                class="form-control" value="${regularHours}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="overtimeHours">Giờ làm thêm:</label>
                            <input id="overtimeHours" type="number" min="0" step="0.5" 
                                class="form-control" value="${overtimeHours}">
                        </div>
                    </div>`}
            </div>
            `,
            didOpen: () => {
                // Initialize project search
                const $projectSearch = $('#project-search');
                const $projectId = $('#project');
                const $searchResults = $('#project-search-results');
                const $searchBtn = $('#search-project-btn');

                function searchProjects() {
                    const searchText = $projectSearch.val().trim();

                    // Extract project code if there's a hyphen in the search text
                    const projectCode = searchText.split('-')[0].trim();

                    $.ajax({
                        url: 'pages/vjs/search_project.php',
                        method: 'GET', // Changed from POST to GET to match server expectation
                        data: {
                            query: projectCode || searchText // Send either project code or full search text
                        },
                        success: function(response) {
                            try {
                                $searchResults.empty();

                                if (response.success && response.projects && response.projects.length > 0) {
                                    response.projects.forEach(project => {
                                        const $item = $(`
                                            <a class="dropdown-item" href="#" data-id="${project.id}">
                                                ${project.ma_du_an} - ${project.name}
                                            </a>
                                        `);

                                        $item.on('click', function(e) {
                                            e.preventDefault();
                                            $projectId.val(project.id);
                                            $projectSearch.val(`${project.ma_du_an} - ${project.name}`);
                                            $searchResults.hide();

                                            // Trigger project change handling
                                            handleProjectChange(project.id);
                                        });

                                        $searchResults.append($item);
                                    });
                                    $searchResults.show();
                                } else {
                                    $searchResults.append(`
                                        <span class="dropdown-item-text">Không tìm thấy dự án</span>
                                    `);
                                    $searchResults.show();
                                }
                            } catch (e) {
                                console.error('Error parsing project search results:', e);
                                $searchResults.empty().append(`
                                    <span class="dropdown-item-text text-danger">Lỗi khi xử lý kết quả tìm kiếm</span>
                                `);
                                $searchResults.show();
                            }
                        },
                        error: function() {
                            $searchResults.empty().append(`
                                <span class="dropdown-item-text text-danger">Lỗi khi tìm kiếm</span>
                            `);
                            $searchResults.show();
                        }
                    });
                }

                // Search button click handler
                $searchBtn.on('click', function() {
                    $projectSearch.prop('readonly', false);
                    $projectSearch.focus();
                    if ($projectSearch.val().trim().length >= 3) {
                        searchProjects();
                    }
                });

                // Handle clicking outside of search results
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#project-search, #project-search-results, #search-project-btn').length) {
                        $searchResults.hide();
                        $projectSearch.prop('readonly', true);
                    }
                });

                // Search input handler with debounce
                let searchTimeout;
                $projectSearch.on('input', function() {
                    clearTimeout(searchTimeout);
                    const searchText = $(this).val().trim();

                    if (searchText.length >= 3) {
                        searchTimeout = setTimeout(searchProjects, 300);
                    } else {
                        $searchResults.empty().append(`
                            <span class="dropdown-item-text">Nhập ít nhất 3 ký tự để tìm kiếm</span>
                        `);
                        $searchResults.show();
                    }
                });

                // Set the current project value
                if (event.extendedProps.projectId) {
                    $('#project').val(event.extendedProps.projectId).trigger('change');
                }

                // Store original project ID for comparison
                const originalProjectId = $('#project').val();

                // Filter tasks when project changes
                $('#project').on('change', function() {
                    const projectId = $(this).val();
                    const taskSelect = $('#task');

                    // Check if project was changed
                    const projectChanged = projectId != originalProjectId;

                    if (taskSelect.length) {
                        // Handle single task selector
                        taskSelect.empty().append('<option value="">Select Task</option>');

                        if (!projectId) {
                            taskSelect.prop('disabled', true);
                            return;
                        }

                        taskSelect.prop('disabled', true);

                        // Show loading indicator
                        taskSelect.append('<option value="" disabled>Loading tasks...</option>');

                        // Fetch tasks for this project - same as before
                        $.ajax({
                            url: 'pages/v-time/api/get_project_tasks.php',
                            method: 'GET',
                            data: {
                                project_id: projectId
                            },
                            dataType: 'json',
                            success: function(response) {
                                taskSelect.empty().append('<option value="">Chọn công việc</option>');

                                if (response.success && response.tasks && response.tasks.length > 0) {
                                    // Add task options
                                    $.each(response.tasks, function(i, task) {
                                        taskSelect.append(`<option value="${task.id}">${task.name}</option>`);
                                    });
                                    taskSelect.prop('disabled', false);
                                } else {
                                    // No tasks found
                                    taskSelect.append('<option value="" disabled>Không có công việc nào cho dự án này</option>');
                                    if (!$('#tasks-table-body').length) {
                                        Swal.showValidationMessage('Dự án này không có công việc. Vui lòng thêm công việc vào dự án trước.');
                                    }
                                }
                            },
                            error: function() {
                                taskSelect.empty().append('<option value="">Chọn công việc</option>')
                                    .append('<option value="" disabled>Lỗi khi tải công việc</option>');
                                if (!$('#tasks-table-body').length) {
                                    Swal.showValidationMessage('Lỗi khi tải công việc. Vui lòng thử lại.');
                                }
                            }
                        });
                    }

                    // Handle task table if project changes
                    if ($('#tasks-table-body').length && projectChanged) {
                        handleProjectChange(projectId);
                    }
                });

                // Add event listeners for task table functionality
                if ($('#tasks-table-body').length) {
                    // Recalculate totals when hours change
                    $(document).on('change', '.task-regular-hours, .task-overtime-hours', updateTotals);

                    // Modified Remove task button handler
                    $(document).on('click', '.remove-task', function() {
                        const $row = $(this).closest('tr');
                        const entryId = $row.data('entry-id');
                        const entryIds = $row.data('entry-ids');

                        // If we have entry ID(s), add them to the deleted list
                        if (entryId) {
                            deletedTaskEntries.push(entryId);
                        } else if (entryIds) {
                            // Handle multiple IDs
                            if (Array.isArray(entryIds)) {
                                entryIds.forEach(id => {
                                    if (id) deletedTaskEntries.push(id);
                                });
                            } else if (typeof entryIds === 'string') {
                                try {
                                    const ids = JSON.parse(entryIds);
                                    if (Array.isArray(ids)) {
                                        ids.forEach(id => {
                                            if (id) deletedTaskEntries.push(id);
                                        });
                                    }
                                } catch (e) {
                                    // If not valid JSON, try comma separated
                                    entryIds.split(',').forEach(id => {
                                        if (id && id.trim()) deletedTaskEntries.push(parseInt(id.trim(), 10));
                                    });
                                }
                            } else if (typeof entryIds === 'number') {
                                deletedTaskEntries.push(entryIds);
                            }
                        }

                        // Now remove the row from UI
                        $row.remove();
                        updateTotals();
                    });

                    // Add new task button
                    $('#add-task-btn').on('click', function() {
                        const projectId = $('#project').val();
                        if (!projectId) {
                            Swal.showValidationMessage('Chọn dự án trước khi thêm công việc');
                            return;
                        }

                        addNewTaskRow(projectId);
                    });

                    // NEW: Make existing task names editable
                    $(document).on('click', '.edit-task', function() {
                        const $row = $(this).closest('tr');
                        const taskId = $row.data('task-id');
                        const taskName = $row.find('.task-name').text();
                        const projectId = $('#project').val();

                        if (!projectId) {
                            Swal.showValidationMessage('Project not selected');
                            return;
                        }

                        // Replace task name with dropdown
                        $row.find('td:first').html(`
                            <select class="form-control form-control-sm task-select">
                                <option value="">Loading tasks...</option>
                            </select>
                        `);

                        // Load tasks for this project
                        loadTasksForSelect($row.find('.task-select'), projectId, taskId);
                    });
                }

                // Function to handle project change in task table
                function handleProjectChange(newProjectId) {
                    // Get all existing task rows
                    const $taskRows = $('#tasks-table-body tr');

                    // Store current modal instance
                    const currentModal = Swal.getPopup();

                    // If no tasks exist, just add a new row
                    if ($taskRows.length === 0) {
                        addNewTaskRow(newProjectId);
                        return;
                    }

                    // Create and style loading overlay
                    const loadingOverlay = $('<div class="task-loading-overlay"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
                    loadingOverlay.css({
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        width: '100%',
                        height: '100%',
                        backgroundColor: 'rgba(255, 255, 255, 0.7)',
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        zIndex: 1000
                    });

                    // Add overlay to table container
                    const $tableContainer = $('#tasks-table-body').closest('.table-responsive');
                    $tableContainer.css('position', 'relative').append(loadingOverlay);

                    // Fetch tasks for the new project
                    $.ajax({
                        url: 'pages/v-time/api/get_project_tasks.php',
                        method: 'GET',
                        data: {
                            project_id: newProjectId
                        },
                        dataType: 'json',
                        beforeSend: function() {
                            // Disable buttons but don't show Swal loading state
                            $('.swal2-confirm, .swal2-cancel, .swal2-deny').prop('disabled', true);
                        },
                        success: function(response) {
                            // Remove loading overlay
                            loadingOverlay.remove();

                            // Re-enable buttons
                            $('.swal2-confirm, .swal2-cancel, .swal2-deny').prop('disabled', false);

                            if (response.success && response.tasks && response.tasks.length > 0) {
                                const tasks = response.tasks;

                                // Update existing rows with new task options
                                $('#tasks-table-body tr').each(function() {
                                    const $row = $(this);
                                    const taskId = $row.data('task-id');
                                    const $taskCell = $row.find('td:first');

                                    // Convert static task cell to dropdown
                                    $taskCell.html(`
                                        <select class="form-control form-control-sm task-select">
                                            <option value="">Chọn công việc</option>
                                            ${tasks.map(task => 
                                                `<option value="${task.id}" ${task.id == taskId ? 'selected' : ''}>${task.name}</option>`
                                            ).join('')}
                                        </select>
                                    `);

                                    // Deselect if task doesn't exist in new project
                                    const $select = $row.find('.task-select');
                                    if ($select.find(`option[value="${taskId}"]`).length === 0) {
                                        $select.val('');

                                        // Highlight fields that need attention
                                        $select.addClass('is-invalid border-danger');
                                        if (!$select.next('.text-danger').length) {
                                            $select.parent().append('<div class="text-danger small mt-1">Vui lòng chọn công việc mới</div>');
                                        }
                                    }

                                    // Update data-task-id when selection changes
                                    $select.on('change', function() {
                                        $row.data('task-id', $(this).val());
                                        // Remove error styling when a valid selection is made
                                        if ($(this).val()) {
                                            $(this).removeClass('is-invalid border-danger');
                                            $(this).parent().find('.text-danger').remove();
                                        }
                                    });
                                });

                                // Show success message without closing modal
                                // Toast.fire({
                                //     icon: 'success',
                                //     title: 'Đã thay đổi dự án. Vui lòng kiểm tra và chọn công việc mới.'
                                // });
                            } else {
                                // Show error and revert project selection
                                // Toast.fire({
                                //     icon: 'error',
                                //     title: 'Không tìm thấy công việc nào trong dự án được chọn.'
                                // });

                                // Revert project selection
                                $('#project').val(originalProjectId);
                                $('.select2-modal').trigger('change.select2');
                            }
                        },
                        error: function(xhr) {
                            // Remove loading overlay
                            loadingOverlay.remove();

                            // Re-enable buttons
                            $('.swal2-confirm, .swal2-cancel, .swal2-deny').prop('disabled', false);

                            Toast.fire({
                                icon: 'error',
                                title: 'Không thể tải công việc cho dự án mới'
                            });

                            // Revert project selection
                            $('#project').val(originalProjectId);
                            $('.select2-modal').trigger('change.select2');
                        }
                    });
                }

                // Function to load tasks into a select element
                function loadTasksForSelect($select, projectId, selectedTaskId) {
                    $.ajax({
                        url: 'pages/v-time/api/get_project_tasks.php',
                        method: 'GET',
                        data: {
                            project_id: projectId
                        },
                        dataType: 'json',
                        success: function(response) {
                            $select.empty();

                            if (response.success && response.tasks && response.tasks.length > 0) {
                                $select.append('<option value="">Select Task</option>');
                                // Add task options
                                $.each(response.tasks, function(i, task) {
                                    $select.append(`<option value="${task.id}" 
                                        ${task.id == selectedTaskId ? 'selected' : ''}>
                                        ${task.name}
                                    </option>`);
                                });

                                // Update task ID when select changes
                                $select.on('change', function() {
                                    $(this).closest('tr').data('task-id', $(this).val());
                                });
                            } else {
                                $select.append('<option value="" disabled>No tasks available</option>');
                            }
                        },
                        error: function() {
                            $select.empty().append('<option value="">Error loading tasks</option>');
                        }
                    });
                }

                // Function to update total hours
                function updateTotals() {
                    let totalRegular = 0;
                    let totalOvertime = 0;

                    $('.task-regular-hours').each(function() {
                        totalRegular += parseFloat($(this).val()) || 0;
                    });

                    $('.task-overtime-hours').each(function() {
                        totalOvertime += parseFloat($(this).val()) || 0;
                    });

                    $('#total-regular-hours').text(totalRegular.toFixed(1));
                    $('#total-overtime-hours').text(totalOvertime.toFixed(1));
                }

                // Initially calculate totals
                let totalRegular = 0;
                let totalOvertime = 0;

                $('.task-regular-hours').each(function() {
                    totalRegular += parseFloat($(this).val()) || 0;
                });

                $('.task-overtime-hours').each(function() {
                    totalOvertime += parseFloat($(this).val()) || 0;
                });

                $('#total-regular-hours').text(totalRegular.toFixed(1));
                $('#total-overtime-hours').text(totalOvertime.toFixed(1));

                function addNewTaskRow(projectId) {
                    // Validate that we have a project ID
                    if (!projectId) {
                        Swal.showValidationMessage('Please select a project first');
                        return;
                    }

                    // Show loading indicator in the table
                    $('#tasks-table-body').append(`
                        <tr id="loading-task-row">
                            <td colspan="4" class="text-center">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                Loading tasks for this project...
                            </td>
                        </tr>
                    `);

                    // Fetch tasks specific to this project
                    $.ajax({
                        url: 'pages/v-time/api/get_project_tasks.php',
                        method: 'GET',
                        data: {
                            project_id: projectId
                        },
                        dataType: 'json',
                        success: function(response) {
                            // Remove loading indicator
                            $('#loading-task-row').remove();

                            try {
                                if (response.success && response.tasks && response.tasks.length > 0) {
                                    // Build dropdown options with only tasks for this project
                                    const tasksOptions = response.tasks.map(task =>
                                        `<option value="${task.id}">${task.name}</option>`
                                    ).join('');

                                    // Add new row with the filtered task options
                                    const newRow = `
                                    <tr>
                                        <td>
                                            <select class="form-control form-control-sm task-select">
                                                <option value="">Select Task</option>
                                                ${tasksOptions}
                                            </select>
                                        </td>
                                        <td><input type="number" class="form-control form-control-sm task-regular-hours" 
                                                   value="0" min="0" step="0.5"></td>
                                        <td><input type="number" class="form-control form-control-sm task-overtime-hours" 
                                                   value="0" min="0" step="0.5"></td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-task">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    `;

                                    $('#tasks-table-body').append(newRow);

                                    // Update data-task-id when task is selected
                                    $('#tasks-table-body tr:last-child .task-select').on('change', function() {
                                        $(this).closest('tr').data('task-id', $(this).val());
                                    });

                                    updateTotals();

                                    // Remove any "no tasks" message if it exists
                                    $('#no-tasks-row').remove();
                                } else {
                                    // Show no tasks message directly in the table
                                    $('#tasks-table-body').append(`
                                        <tr id="no-tasks-row">
                                            <td colspan="4" class="text-center text-warning">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                No tasks available for this project. Please add tasks to the project first.
                                            </td>
                                        </tr>
                                    `);
                                }
                            } catch (e) {
                                console.error('Error parsing tasks:', e);
                                Swal.showValidationMessage('Error loading tasks');
                            }
                        },
                        error: function(xhr, status, error) {
                            // Remove loading indicator
                            $('#loading-task-row').remove();

                            console.error('AJAX error:', status, error);
                            Swal.showValidationMessage('Failed to load tasks for this project');

                            // Show error in table
                            $('#tasks-table-body').append(`
                                <tr>
                                    <td colspan="4" class="text-center text-danger">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Error loading tasks. Please try again.
                                    </td>
                                </tr>
                            `);
                        }
                    });
                }
            },
            focusConfirm: false,
            showDenyButton: true,
            confirmButtonText: 'Update',
            denyButtonText: 'Delete',
            showCancelButton: true,
            preConfirm: () => {
                const project = $('#project').val();

                if (!project) {
                    Swal.showValidationMessage('Please select a project');
                    return false;
                }

                // If we have the task table, collect all task data
                if ($('#tasks-table-body').length) {
                    const tasks = [];
                    let hasData = false;

                    $('#tasks-table-body tr').each(function() {
                        const $row = $(this);
                        const taskId = $row.data('task-id') || $row.find('.task-select').val();

                        if (!taskId) {
                            // Skip rows with no task selected
                            return;
                        }

                        // Fix for entry IDs handling
                        const entryIds = $row.data('entry-ids');
                        let entryIdArray = [];

                        if (entryIds) {
                            // Handle different possible types of entry IDs
                            if (Array.isArray(entryIds)) {
                                // If it's already an array, use it directly
                                entryIdArray = entryIds.map(id => parseInt(id, 10));
                            } else if (typeof entryIds === 'string') {
                                try {
                                    // Try to parse JSON first
                                    const parsed = JSON.parse(entryIds);
                                    if (Array.isArray(parsed)) {
                                        entryIdArray = parsed.map(id => parseInt(id, 10));
                                    } else {
                                        // If it's not an array, split by comma
                                        entryIdArray = entryIds.split(',')
                                            .map(id => id.trim())
                                            .filter(id => id)
                                            .map(id => parseInt(id, 10));
                                    }
                                } catch (e) {
                                    // If parsing fails, split by comma
                                    entryIdArray = entryIds.split(',')
                                        .map(id => id.trim())
                                        .filter(id => id)
                                        .map(id => parseInt(id, 10));
                                }
                            } else if (typeof entryIds === 'number') {
                                // If it's a single number, put it in an array
                                entryIdArray = [entryIds];
                            }
                        }

                        // Ensure we don't have NaN values
                        entryIdArray = entryIdArray.filter(id => !isNaN(id));

                        const regularHours = parseFloat($row.find('.task-regular-hours').val()) || 0;
                        const overtimeHours = parseFloat($row.find('.task-overtime-hours').val()) || 0;

                        if (taskId && (regularHours > 0 || overtimeHours > 0)) {
                            tasks.push({
                                task_id: taskId,
                                regular_hours: regularHours,
                                overtime_hours: overtimeHours,
                                entry_ids: entryIdArray
                            });
                            hasData = true;
                        }
                    });

                    if (!hasData) {
                        Swal.showValidationMessage('Please add at least one task with hours');
                        return false;
                    }

                    return {
                        project,
                        tasks,
                        deletedTaskEntries, // Include the list of tasks to delete
                        originalProjectId: event.extendedProps.projectId, // Include original project ID for tracking changes
                        date: event.startStr // Include date
                    };
                } else {
                    // Fallback to original single task form
                    const task = $('#task').val();
                    const regularHours = parseFloat($('#regularHours').val()) || 0;
                    const overtimeHours = parseFloat($('#overtimeHours').val()) || 0;

                    if (!task) {
                        Swal.showValidationMessage('Please select a task');
                        return false;
                    }

                    if (regularHours <= 0 && overtimeHours <= 0) {
                        Swal.showValidationMessage('Please enter hours greater than 0');
                        return false;
                    }

                    return {
                        project,
                        task,
                        regularHours,
                        overtimeHours,
                        originalProjectId: event.extendedProps.projectId,
                        date: event.startStr
                    };
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                updateEvent(event, result.value);
            } else if (result.isDenied) {
                deleteEvent(event);
            }
        });
    }

    function deleteEvent(event) {
        // Kiểm tra xem sự kiện có bị khóa không
        if (event.extendedProps.isLocked) {
            Swal.fire({
                icon: 'warning',
                title: 'Không thể xóa',
                text: 'Giờ công này đã bị khóa và không thể xóa. Vui lòng liên hệ quản lý nếu cần thay đổi.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        // Tiếp tục với mã hiện tại nếu không bị khóa
        if (!event || !confirm('Are you sure you want to delete this time entry?')) {
            return;
        }

        // Check if we have the actual entry IDs from the event details
        const eventDate = event.startStr;
        const projectId = event.extendedProps.projectId;
        const nhanvienId = <?= $viewingEmployeeId ?>;

        // First, fetch the actual entry IDs for this project/date combination
        $.ajax({
            url: 'pages/v-time/api/get_event_details.php',
            method: 'GET',
            data: {
                date: eventDate,
                project_id: projectId,
                nhanvien_id: nhanvienId
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success && response.tasks) {
                    // Extract all entry IDs
                    const entryIds = response.tasks.map(task => task.id);

                    //console.log("📊 Found entry IDs to delete:", entryIds);

                    if (entryIds.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No time entries found to delete'
                        });
                        return;
                    }

                    // Now send the delete request with all actual entry IDs
                    const dummyTasks = response.tasks.map(task => ({
                        task_id: task.task_id,
                        regular_hours: 0,
                        overtime_hours: 0
                    }));

                    $.ajax({
                        url: 'pages/v-time/update_event.php',
                        method: 'POST',
                        data: {
                            date: eventDate,
                            project_id: projectId,
                            nhanvien_id: nhanvienId,

                            deleted_task_entries: JSON.stringify(entryIds),
                            tasks: JSON.stringify(dummyTasks)
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.success) {
                                event.remove();
                                Toast.fire({
                                    icon: 'success',
                                    title: 'Time entry deleted successfully'
                                });
                                calendar.refetchEvents();
                                updateProjectSummary(calendar.view.currentStart, calendar.view.currentEnd);
                            } else {
                                //console.error("❌ DELETE EVENT - Server error:", data.message);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to delete time entry'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            //console.error('❌ DELETE EVENT - Exception:', error, xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while deleting the time entry'
                            });
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Could not find time entries to delete'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to retrieve time entries'
                });
            }
        });
    }

    // Thêm hàm kiểm tra xem ngày có bị khóa không
    function checkIfDateIsLocked(dateStr) {
        // First check existing events (for immediate response)
        const events = calendar.getEvents().filter(event => event.startStr === dateStr);

        // If there are events and any of them are locked, return true immediately
        if (events.length > 0 && events.some(event => event.extendedProps.isLocked)) {
            return true;
        }

        // For dates with no events or where no events are locked, check with the server
        // Use a synchronous request to ensure we get a result before proceeding
        let isLocked = false;
        $.ajax({
            url: 'pages/v-time/api/check_date_locked.php',
            method: 'GET',
            async: false, // Make synchronous for simplicity
            data: {
                date: dateStr,
                employee_id: <?= $viewingEmployeeId ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.isLocked) {
                    isLocked = true;
                }
            },
            error: function() {
                // On error, assume it's not locked (fail open to avoid blocking valid operations)
                console.error('Failed to check lock status for date: ' + dateStr);
            }
        });

        return isLocked;
    }

    // Debug logging function - uncomment to enable
    function debugLockStatus() {
        // console.group('Calendar Events Lock Status');
        calendar.getEvents().forEach(event => {
            // console.log(
            //     `Event ID: ${event.id}, Date: ${event.startStr}, ` +
            //     `Project: ${event.extendedProps.projectcode}, ` +
            //     `isLocked: ${event.extendedProps.isLocked === true ? 'YES' : 'NO'}`
            // );
        });
        // console.groupEnd();
    }

    // Gọi hàm này sau khi tải sự kiện
    // calendar.on('eventDidMount', function() {
    //     // Uncomment to debug
    //     // debugLockStatus();
    // });

    function isHoliday(dateStr) {
        const cell = document.querySelector(`.fc-day[data-date="${dateStr}"]`);
        return cell && (cell.classList.contains('holiday-public') || cell.classList.contains('holiday-company'));
    }
</script>