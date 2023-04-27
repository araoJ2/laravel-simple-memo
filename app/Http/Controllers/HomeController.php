<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memo;
use App\Models\Tag;
use App\Models\FIle;
use App\Models\MemoTag;
use DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SharedLink;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $tags = Tag::where('user_id', '=', \Auth::id())->whereNull('deleted_at')->orderBy('id', 'DESC')->get();

        return view('create', compact('tags'));
    }

    public function store(Request $request)
    {
        $posts = $request->all();
        $request->validate(['content' => 'required']);
        // dump dieの略 → メソッドの引数の取った値を展開して止める → データ確認

        // ===== ここからトランザクション開始 ======
        DB::transaction(function () use ($posts) {
            // メモIDをインサートして取得
            $memo_id = Memo::insertGetId(['content' => $posts['content'], 'user_id' => \Auth::id()]);
            $tag_exists = Tag::where('user_id', '=', \Auth::id())->where('name', '=', $posts['new_tag'])->exists();
            // 新規タグが入力されているかチェック
            // 新規タグが既にtagsテーブルに存在するのかチェック
            if (!empty($posts['new_tag']) && !$tag_exists) {
                // 新規タグが既に存在しなければ、tagsテーブルにインサート→IDを取得
                $tag_id = Tag::insertGetId(['user_id' => \Auth::id(), 'name' => $posts['new_tag']]);
                // memo_tagsにインサートして、メモとタグを紐付ける
                MemoTag::insert(['memo_id' => $memo_id, 'tag_id' => $tag_id]);
            }
            // 既存タグが紐付けられた場合→memo_tagsにインサート
            if (!empty($posts['tags'][0])) {
                foreach ($posts['tags'] as $tag) {
                    MemoTag::insert(['memo_id' => $memo_id, 'tag_id' => $tag]);
                }
            }
            if (isset($posts['file'])) {
                $file = $posts['file'];
                $file_name = $file->getClientOriginalName();
                $file_path = $file->storeAs('public', $file_name);
                File::insertGetId(['user_id' => \Auth::id(), 'memo_id' => $memo_id, 'file_path' => $file_path, 'file_name' => $file_name]);
            }
        });
        // ===== ここまでがトランザクションの範囲 ======


        return redirect(route('home'));
    }

    public function edit($id)
    {
        $edit_memo = Memo::select('memos.*', 'tags.id AS tag_id', 'files.*')
            ->leftJoin('memo_tags', 'memo_tags.memo_id', '=', 'memos.id')
            ->leftJoin('tags', 'memo_tags.tag_id', '=', 'tags.id')
            ->leftJoin('files', 'files.memo_id', '=', 'memos.id')
            ->where('memos.user_id', '=', \Auth::id())
            ->where('memos.id', '=', $id)
            ->whereNull('memos.deleted_at')
            ->whereNull('files.deleted_at')
            ->get();

        $include_tags = [];
        foreach ($edit_memo as $memo) {
            array_push($include_tags, $memo['tag_id']);
        }

        $tags = Tag::where('user_id', '=', \Auth::id())->whereNull('deleted_at')->orderBy('id', 'DESC')->get();

        return view('edit', compact('edit_memo', 'include_tags', 'tags'));
    }

    public function update(Request $request)
    {
        $posts = $request->all();
        $request->validate(['content' => 'required']);
        $request = request();

        // トランザクションスタート
        DB::transaction(function () use ($posts, $request) { // $requestをuseする
            Memo::where('id', $posts['memo_id'])->update(['content' => $posts['content']]);
            // 一旦メモとタグの紐付けを削除
            MemoTag::where('memo_id', '=', $posts['memo_id'])->delete();
            // 再度メモとタグの紐付け
            if (!empty($posts['tags'])) {
                foreach ($posts['tags'] as $tag) {
                    MemoTag::insert(['memo_id' => $posts['memo_id'], 'tag_id' => $tag]);
                }
            }
            // 新規タグが入力されているかチェック
            // もし、新しいタグの入力があれば、インサートして紐付ける
            $tag_exists = Tag::where('user_id', '=', \Auth::id())->where('name', '=', $posts['new_tag'])->exists();
            // 新規タグが既にtagsテーブルに存在するのかチェック
            if (!empty($posts['new_tag']) && !$tag_exists) {
                // 新規タグが既に存在しなければ、tagsテーブルにインサート→IDを取得
                $tag_id = Tag::insertGetId(['user_id' => \Auth::id(), 'name' => $posts['new_tag']]);
                // memo_tagsにインサートして、メモとタグを紐付ける
                MemoTag::insert(['memo_id' => $posts['memo_id'], 'tag_id' => $tag_id]);
            }

            if (isset($posts['file'])) {
                $file = $posts['file'];
                $file_name = $file->getClientOriginalName();
                $file_path = $file->storeAs('public', $file_name);
                File::insertGetId(['user_id' => \Auth::id(), 'memo_id' => $posts['memo_id'], 'file_path' => $file_path, 'file_name' => $file_name]);
            }
        });
        // トランザクションここまで

        return redirect(route('home'));
    }


    public function destroy(Request $request)
    {
        $posts = $request->all();

        // Memo::where('id', $posts['memo_id'])->delete();←NGこれやると物理削除
        Memo::where('id', $posts['memo_id'])->update(['deleted_at' => date("Y-m-d H:i:s", time())]);
        File::where('memo_id', $posts['memo_id'])->update(['deleted_at' => date("Y-m-d H:i:s", time())]);

        return redirect(route('home'));
    }
    public function download($id)
    {
        $file_info = File::select('files.*')
            ->where('file_id', '=', $id)
            ->where('user_id', '=', \Auth::id())
            ->whereNull('deleted_at')
            ->get();

        $filePath = $file_info[0]['file_path'];
        $fileName = $file_info[0]['file_name'];

        $mimeType = Storage::mimeType($filePath);
        $headers = [['Content-Type' => $mimeType]];

        return Storage::download($filePath, $fileName, $headers);
    }
}
