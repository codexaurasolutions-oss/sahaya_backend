<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Language;
use App\Models\HowItWork;
use App\Models\HowItWorkDescription;
use App\Services\Admin\SectionService;
use App\Http\Requests\Admin\SectionRequest;


class HowItWorkCustomerController extends Controller
{
    public $model = 'how-it-works-customer';
    public function __construct(Request $request)
    {
        View()->share('model', $this->model);
        View()->share('modelName', $this->model);
        $this->request = $request;
    }

    public function index(Request $request)
    {
        if ($request->isMethod('post')) {
            $thisData = $request->all();
            $default_language = Config('constants.DEFAULT_LANGUAGE.FOLDER_CODE');
            $language_code = Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
            $defaultLanguageArray = $thisData['data'][$language_code];

            $validator = Validator::make(
                [
                    'title'         => $defaultLanguageArray['title'],
                    'description'   => $defaultLanguageArray['description'],
                    'image'         => $request->file('image'),
                ],
                [
                    'title'         => 'required|array|min:1',
                    'title.*'       => 'required|string|max:255',
                    'description'   => 'required|array|min:1',
                    'description.*' => 'required|string|max:255',
                    // 'image'      => 'required|mimes:jpg,jpeg,png',
                ],
                [
                    'title.*.required' => 'The title field is required',
                    'description.*.required' => 'The description field is required'
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            } elseif(count($defaultLanguageArray['title']) > 4) {
                return redirect()->back()->with('error', 'You cant not add more than four fields')->withInput();
            }
            else {
                foreach ($defaultLanguageArray['title'] as $index => $title) {
                    $obj = isset($defaultLanguageArray['id'][$index])
                        ? HowItWork::find($defaultLanguageArray['id'][$index])
                        : new HowItWork();

                    $obj->type = 'customer';
                    $obj->title = $title;
                    $obj->description = $defaultLanguageArray['description'][$index];
                    $obj->save();

                    $lastId = $obj->id;

                    foreach ($thisData['data'] as $language_id => $value) {
                        if (isset($value['title'][$index]) && isset($value['description'][$index])) {
                            $existingDescription = HowItWorkDescription::where('parent_id', $lastId)
                                ->where('language_id', $language_id)
                                ->first();

                            if ($existingDescription) {
                                $existingDescription->title = $value['title'][$index];
                                $existingDescription->description = $value['description'][$index];
                                $existingDescription->save();
                            } else {
                                $subObj = new HowItWorkDescription();
                                $subObj->language_id = $language_id;
                                $subObj->parent_id = $lastId;
                                $subObj->title = $value['title'][$index];
                                $subObj->description = $value['description'][$index];
                                $subObj->save();
                            }
                        }
                    }
                }

                // $obj->image = $this->upload($request, 'image', config('constants.INTRO_SECTION_IMAGE_ROOT_PATH'));

                Session()->flash('success', ucfirst('How It Works Customer' . " has been added successfully"));
                return Redirect()->route($this->model . ".index");
            }
        }


        $languages      =   Language::where('is_active', 1)->get();
        $language_code  =   Config('constants.DEFAULT_LANGUAGE.LANGUAGE_CODE');
        $modelDetail    =   HowItWork::where('type', 'customer')->get();

        $multiLanguage  =   array();
        foreach($modelDetail as $index => $model) {
            $intro_descriptiondetl = HowItWorkDescription::where('parent_id', $model->id)->get();
            if (!empty($intro_descriptiondetl)) {
                foreach ($intro_descriptiondetl as $d) {
                    $multiLanguage[$d->language_id][] = [
                        'id' => $d->id,
                        'title' => $d->title,
                        'description' => $d->description,
                    ];
                }
            }
        }
        // dd($multiLanguage);
        return View("admin.$this->model.index", compact('modelDetail', 'multiLanguage', 'languages', 'language_code'));
    }

    public function destroy($encmsid)
    {
        // dd($encmsid);
        if($encmsid != '') {
            $encmsid = base64_decode($encmsid);
        } else {
            return redirect()->back();
        }
        $obj = HowItWork::find($encmsid);
        if($obj) {
            HowItWorkDescription::where('parent_id', $obj->id)->delete();
            $obj->delete();

            return redirect()->back()->with('success', ucfirst('How It Works Customer' . " has been removed successfully"));
        } else {
            return redirect()->back();
        }
    }

}
