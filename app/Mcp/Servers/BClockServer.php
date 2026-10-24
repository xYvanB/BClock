<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetMyWorkloadTool;
use App\Mcp\Tools\ListMyAbsencesTool;
use App\Mcp\Tools\ListMyActivitiesTool;
use App\Mcp\Tools\ListMyTimeEntriesTool;
use App\Mcp\Tools\ListNotificationsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\LogTimeEntryTool;
use App\Mcp\Tools\MarkNotificationsReadTool;
use App\Mcp\Tools\RequestAbsenceTool;
use App\Mcp\Tools\StartTimerTool;
use App\Mcp\Tools\StopTimerTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('BClock')]
#[Version('1.0.0')]
#[Instructions('BClock tracks working hours, tasks, projects and absences. Every tool acts as the person who authorized this app, with that person\'s own permissions. To log time or start a timer first find the task id with list_my_activities. Dates are YYYY-MM-DD and times HH:MM. Nothing can be deleted, approved or reconfigured from here.')]
class BClockServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListMyActivitiesTool::class,
        ListMyTimeEntriesTool::class,
        ListMyAbsencesTool::class,
        GetMyWorkloadTool::class,
        ListNotificationsTool::class,
        ListProjectsTool::class,
        LogTimeEntryTool::class,
        StartTimerTool::class,
        StopTimerTool::class,
        RequestAbsenceTool::class,
        MarkNotificationsReadTool::class,
    ];
}
