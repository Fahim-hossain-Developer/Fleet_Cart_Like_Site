@foreach($nodes as $node)
@if(!$ancestors->contains($node->id))
<li class="ml-3"><details @if($loop->first) open @endif><summary class="min-h-11 cursor-pointer py-3"><a href="{{ route('category',$node->slug) }}" class="hover:text-brand">{{ $node->name }}</a></summary>@if($categories->where('parent_id',$node->id)->isNotEmpty())<ul>@include('themes.default.partials.category-tree',['nodes'=>$categories->where('parent_id',$node->id),'ancestors'=>$ancestors->concat([$node->id])])</ul>@endif</details></li>
@endif
@endforeach
