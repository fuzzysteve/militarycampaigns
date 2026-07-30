<html>
  <head>
    <title>Military Campaigns - {block name=title}{/block}</title>
    <link href="{$baseurl}/css/main.css" rel="stylesheet" type="text/css"/>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>

{block name=extrajavascript}{/block}

{include file='file:/home/web/fuzzwork/htdocs/bootstrap/header.php' }
</head>
<body {block name=bodyattributes}{/block}>
{nocache}
{include_php file='/home/web/fuzzwork/htdocs/menu/menubootstrap.php' }
{/nocache}
<div class='container'>
{block name=body}{/block}
</div>
{include file='file:/home/web/fuzzwork/htdocs/bootstrap/footer.php' }
