<?php

namespace App\Http\Controllers;

use App\Models\ClientProposal;
use App\Models\Project;
use App\Models\WpBuild;
use App\Support\MockupCodexState;
use App\Support\MockupGate;
use Illuminate\Http\Request;

class AiWorkspaceController extends Controller
{
    public function aiWorkspace(Request $request, MockupGate $gate)
    {
        $projects = Project::latest()->get();
        $project = null;
        $mockupSet = collect();
        $clientProposal = null;
        $wpBuild = null;
        $codexStatus = null;
        if ($request->project) {
            $project = Project::find($request->project);
            if ($project && in_array('website', $project->serviceTypes())) {
                $mockupSet = $gate->currentSet($project);
                $clientProposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();
                $wpBuild = WpBuild::where('project_id', $project->id)->first();

                $codexState = app(MockupCodexState::class);
                $codexStatus = $codexState->visible($project);

                if (is_array($codexStatus)) {
                    $codexStatus['heartbeat_seconds'] = $codexState->heartbeatAgeSeconds($project);
                    $codexStatus['alive'] = $codexState->sedangDiproses($project);
                }
            }
        }

        return view('pages.project-workspace', compact('projects', 'project', 'mockupSet', 'clientProposal', 'wpBuild', 'codexStatus'));
    }

    public function proposalWorkshop(Request $request)
    {
        // Keep old /workshop bookmarks working.
        return redirect()->route('pages.project-workspace', $request->filled('project') ? [
            'project' => $request->integer('project'),
        ] : []);
    }
}
