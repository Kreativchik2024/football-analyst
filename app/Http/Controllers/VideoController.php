<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index()
    {
        $videos = Video::latest()->get();
        return view('videos.index', compact('videos'));
    }

    public function create()
    {
        return view('videos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'embed_code' => 'required',
        ]);

        Video::create([
            'title' => $request->title,
            'embed_code' => $request->embed_code,
            'is_active' => true,
        ]);

        return redirect()->route('videos.index')->with('success', 'Видео добавлено');
    }

    public function edit(Video $video)
    {
        return view('videos.edit', compact('video'));
    }

    public function update(Request $request, Video $video)
    {
        $request->validate([
            'embed_code' => 'required',
        ]);

        $video->update([
            'title' => $request->title,
            'embed_code' => $request->embed_code,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('videos.index')->with('success', 'Видео обновлено');
    }

    public function destroy(Video $video)
    {
        $video->delete();
        return redirect()->route('videos.index')->with('success', 'Видео удалено');
    }
}