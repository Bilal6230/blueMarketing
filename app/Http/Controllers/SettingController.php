<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class SettingController extends Controller
{
    public function index()
    {
        $x['title']     = 'Setting';
        $x['types'] = Ledger::distinct()->pluck('type')->toArray();
        $x['category']  = Setting::where('type', '!=', 'toggle')->select('category')->groupBy('category')->get();
        return view('admin.setting', $x);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'  => ['required', 'string'],
            'role'      => ['required']
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        try {
            $setting = Setting::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password)
            ]);
            $setting->assignRole($request->role);
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully Save')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to Save : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function show(Request $request)
    {
        $setting = Setting::find($request->id);
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data setting by id',
            'data'      => $setting
        ], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['nullable'],
            'toggles' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            /**
             * Normal settings (text, textarea, file)
             */
            if ($request->filled('key')) {
                foreach ($request->key as $i => $key) {
                    $keyValue = str_replace('_', ' ', $key);
                    $value = $request->value[$i] ?? null;

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'text',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            /**
             * Toggle settings
             */
            if ($request->filled('toggles')) {
                foreach ($request->toggles as $key => $value) {
                    $keyValue = str_replace('_', ' ', $key);

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'toggle',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            Alert::success('Notification', 'Settings saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Settings failed to save : ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

    public function destroy(Request $request)
    {
        try {
            $setting = Setting::find($request->id);
            $setting->delete();
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to delete : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
}
