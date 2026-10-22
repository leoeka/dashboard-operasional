<?php

namespace App\Http\Controllers;

use App\Models\ClientProposal;
use App\Models\Project;
use App\Models\WpBuild;
use App\Support\MockupGate;
use Illuminate\Http\Request;

class AiWorkspaceController extends Controller
{
    public function aiWorkspace(Request $request, MockupGate $gate)
    {
        $project = null;
        $mockupSet = collect();
        $clientProposal = null;
        $wpBuild = null;
        if ($request->project) {
            $project = Project::find($request->project);
            if ($project && in_array('website', $project->serviceTypes())) {
                $mockupSet = $gate->currentSet($project);
                $clientProposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();
                $wpBuild = WpBuild::where('project_id', $project->id)->first();
            }
        }

        return view('pages.project-workspace', compact('project', 'mockupSet', 'clientProposal', 'wpBuild'));
    }

    public function proposalWorkshop(Request $request)
    {
        // Keep old /workshop bookmarks working.
        return redirect()->route('pages.project-workspace', $request->filled('project') ? [
            'project' => $request->integer('project'),
        ] : []);
    }
}

