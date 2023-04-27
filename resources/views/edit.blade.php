@extends('layouts.app')
@section('javascript')
<script src="/js/confirm.js"></script> 
@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        メモ編集
        <form id="delete-form" action="{{route('destroy')}}" method="POST">
            @csrf
            <input type="hidden" name="memo_id" value="{{$edit_memo[0]['id']}}"/> 
            <i class="fas fa-trash" style="margin-right: 3px;" onclick="deleteHandle(event);"></i>
        </form>
    </div>
    <form class="my-card-body card-body" action="{{ route('update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="memo_id" value="{{$edit_memo[0]['id']}}"/>
        <div class="from-group">
            <textarea class="form-control" name="content" rows="3" placeholder="ここにメモを入力">{{
            $edit_memo[0]['content'] }}</textarea>
        </div>
        @error('content')
        <div class="alert alert-danger">メモ内容を入力してください!</div>
        @enderror
    @foreach($tags as $tag)
        <div class="form-check form-check-inline mb-3">
            <input class="form-check-input" type="checkbox" name="tags[]" id="{{$tag['id']}}" 
            value="{{$tag['id']}}" {{in_array($tag['id'],$include_tags)?'checked' :''}}>
            <label class="form-check-label" for="{{$tag['id']}}">{{$tag['name']}}</label>
        </div>
    @endforeach
        <input type="text" class="form-control w-50 mb-3" name="new_tag" placeholder="新しいタグを入力"/>
            <div class="w-50 mb-3">
                @if ($edit_memo[0]['file_id']!=null)
                <a id="download-link" class="mb-3" href="{{ route('download' , ['id' => $edit_memo[0]['file_id']])}}">{{$edit_memo[0]['file_name']}}</a>
                <br>※ダウンロードファイルは編集できません
                @else
                @csrf
                <input type="file" id="file" name="file" class="form-control mb-3">
                @endif
                <div class="w-50 mb-3">
                    <button type="submit" class="btn btn-primary mb-3">更新</button>
                </div>
            </div>
    </form>
</div>
@endsection
