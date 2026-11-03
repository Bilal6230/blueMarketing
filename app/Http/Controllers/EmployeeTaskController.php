<?php

namespace App\Http\Controllers;

use App\Models\EmployeeTask;
use App\Models\User;
use App\Notifications\EmployeeTaskNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeTaskController extends Controller
{
    private function manager(): bool { return auth()->user()->hasAnyRole(['superadmin','admin']); }
    private function scope()
    {
        $q = EmployeeTask::with(['assignee:id,name','creator:id,name'])->where('project_id', (int) getSelectedTown());
        return $this->manager() ? $q : $q->where('assigned_to', auth()->id());
    }
    public function index(Request $request)
    {
        $data = $request->validate(['status'=>['nullable','in:new,pending,in_progress,completed,rework,cancelled,overdue,due_today'],
            'employee_id'=>['nullable','integer','min:1']]);
        $q = $this->scope();
        if (($data['status'] ?? null) === 'overdue') $q->whereNotIn('status',['completed','cancelled'])->whereDate('due_date','<',now());
        elseif (($data['status'] ?? null) === 'due_today') $q->whereNotIn('status',['completed','cancelled'])->whereDate('due_date',now());
        elseif (!empty($data['status'])) $q->where('status',$data['status']);
        if ($this->manager() && !empty($data['employee_id'])) $q->where('assigned_to',$data['employee_id']);
        return view('admin.employee-tasks.index', ['title'=>'Employee Tasks','tasks'=>$q->latest()->paginate(20)->withQueryString(),
            'employees'=>User::where('project_id',getSelectedTown())->where('status_id',1)->orderBy('name')->get(['id','name']),
            'canManage'=>$this->manager()]);
    }
    public function store(Request $request)
    {
        abort_unless($this->manager(),403);
        $data=$this->validated($request); $data['project_id']=(int)getSelectedTown(); $data['created_by']=auth()->id();
        $task=EmployeeTask::create($data); $this->notify($task->assignee,$task,'A new task was assigned to you.');
        return back()->with('success','Task created successfully.');
    }
    public function update(Request $request, EmployeeTask $employeeTask)
    {
        abort_unless($this->manager() && $employeeTask->project_id==(int)getSelectedTown(),403);
        $oldAssignee=$employeeTask->assigned_to; $employeeTask->update($this->validated($request));
        $this->notify($employeeTask->assignee,$employeeTask,$oldAssignee===$employeeTask->assigned_to?'Your task was updated.':'A task was assigned to you.');
        return back()->with('success','Task updated successfully.');
    }
    public function status(Request $request, EmployeeTask $employeeTask)
    {
        abort_unless($employeeTask->project_id==(int)getSelectedTown() && ($this->manager() || $employeeTask->assigned_to===auth()->id()),403);
        $allowed=$this->manager()?['new','pending','in_progress','completed','rework','cancelled']:['pending','in_progress','completed'];
        $data=$request->validate(['status'=>['required',Rule::in($allowed)], 'completion_note'=>[Rule::requiredIf($request->input('status')==='rework'),'nullable','string','max:2000']]);
        $data['completed_at']=$data['status']==='completed'?now():null; $employeeTask->update($data);
        if ($this->manager()) $this->notify($employeeTask->assignee,$employeeTask,'Task status changed to '.str_replace('_',' ',$data['status']).'.');
        else $this->notify($employeeTask->creator,$employeeTask,auth()->user()->name.' changed task status to '.str_replace('_',' ',$data['status']).'.');
        return back()->with('success','Task status updated.');
    }
    public function destroy(EmployeeTask $employeeTask)
    {
        abort_unless($this->manager() && $employeeTask->project_id==(int)getSelectedTown(),403);
        $employeeTask->delete(); return back()->with('success','Task archived.');
    }
    public function readNotification(string $notification)
    {
        $item=auth()->user()->notifications()->whereKey($notification)->firstOrFail();
        abort_unless((int)($item->data['project_id']??0)===(int)getSelectedTown(),403);
        $item->markAsRead();
        return redirect()->route('employee-tasks.index');
    }
    private function validated(Request $request): array
    {
        return $request->validate(['assigned_to'=>['required','integer',Rule::exists('users','id')->where(fn($q)=>$q->where('project_id',(int)getSelectedTown())->where('status_id',1))],'title'=>['required','string','max:255'],
            'description'=>['nullable','string','max:5000'],'priority'=>['required','in:low,medium,high,urgent'],
            'status'=>['required','in:new,pending,in_progress,completed,rework,cancelled'],'start_date'=>['nullable','date'],
            'due_date'=>['nullable','date','after_or_equal:start_date']]);
    }
    private function notify(?User $user, EmployeeTask $task, string $message): void
    {
        if ($user && $user->id!==auth()->id()) $user->notify(new EmployeeTaskNotification($task,$message));
    }
}
