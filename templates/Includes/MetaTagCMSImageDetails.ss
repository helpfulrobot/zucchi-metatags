<% if CMSThumbnail %>
	<div class="iconHolder"><a href="$Link" class="newWindow bold">$CMSThumbnail</a></div>
<% end_if %>
<% if Error %>
	<div class="errorHolder" style="color: red">ERROR: $Error</div>
<% end_if %>
<div class="fileInfo">
	<% if getFileType %><br /><span class="label">Type:</span> <span class="data">$getFileType</span><% end_if %>
	<% if getSize %><br /><span class="label">Size:</span> <span class="data">$getSize</span><% end_if %>
	<% if getDimensions %><br /><span class="label">Dimensions:</span> <span class="data">$getDimensions</span><% end_if %>
</div>
