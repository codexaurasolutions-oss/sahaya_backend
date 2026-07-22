<div class="row">
	<div class="col-sm-12 col-md-5">
		<div class="dataTables_info">
			Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{$results->total()}} entries
		</div>
	</div>
	<div class="col-sm-12 col-md-7">
		<div class="d-flex justify-content-end align-items-center">
			<div class="dataTables_length mr-4">
			@php 
				if(isset($_GET['per_page'])){
					$per_page 		= $_GET['per_page'];
				}else{
					$per_page 		= 10;
				}
			@endphp
				<label class="mb-0">
					Show
					<select name="per_page" class="class' => 'custom-select custom-select-sm form-control form-control-sm" onchange='page_limit()' id="per_page">
						<option Value="10" selected>Default</option>
						<option Value="15" {{$per_page == 15 ? 'selected' : ''}}>15</option>
						<option Value="20" {{$per_page == 20 ? 'selected' : ''}}>20</option>
						<option Value="30" {{$per_page == 30 ? 'selected' : ''}}>30</option>
						<option Value="50" {{$per_page == 50 ? 'selected' : ''}}>50</option>
						<option Value="100"{{$per_page == 100 ? 'selected' : ''}}>100</option>
					</select>
				</label>
			</div>
			<?php
			$link_limit = 6;
			?>
			@if ($results->lastPage() > 1)
			<div class="dataTables_paginate paging_full_numbers">
				<ul class="pagination">
					@if ($results->onFirstPage())
					<li class="paginate_button page-item previous disabled">
						<a href="javascript:void(0);" class="page-link">
							<i class="ki ki-arrow-back"></i>
						</a>
					</li>
					@else
					<li class="paginate_button page-item first">
						<a href="{{ $results->url(1)  }}" class="page-link">
							<i class="ki ki-double-arrow-back"></i>
						</a>
					</li>
					<li class="paginate_button page-item previous">
						<a href="{{ $results->previousPageUrl() }}" class="page-link">
							<i class="ki ki-arrow-back"></i>
						</a>
					</li>
					@endif
					@for ($i = 1; $i <= $results->lastPage(); $i++)
						<?php
						$half_total_links = floor($link_limit / 2);
						$from = $results->currentPage() - $half_total_links;
						$to = $results->currentPage() + $half_total_links;
						if ($results->currentPage() < $half_total_links) {
							$to += $half_total_links - $results->currentPage();
						}
						if ($results->lastPage() - $results->currentPage() < $half_total_links) {
							$from -= $half_total_links - ($results->lastPage() - $results->currentPage()) - 1;
						}
						?>
						@if ($from < $i && $i < $to) <li class="paginate_button page-item {{ ($results->currentPage() == $i) ? ' active' : '' }}">
							<a href='{{ $results->url("$i") }}' class="page-link">{{ $i }}</a>
							</li>
							@endif
							@endfor
							@if ($results->hasMorePages())
							<li class="paginate_button page-item next ">
								<a href="{{ $results->nextPageUrl() }}" class="page-link">
									<i class="ki ki-arrow-next"></i>
								</a>
							</li>
							<li class="paginate_button page-item last">
								<a href="{{ $results->url($results->lastPage()) }}" class="page-link">
									<i class="ki ki-double-arrow-next"></i>
								</a>
							</li>
							@else
							<li class="paginate_button page-item next disabled">
								<a href="#" class="page-link">
									<i class="ki ki-arrow-next"></i>
								</a>
							</li>
							@endif
				</ul>
			</div>
			@endif
		</div>
	</div>
</div>