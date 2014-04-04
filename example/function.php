<?php
/**
 * Created by PhpStorm.
 * User: EC
 * Date: 04.04.14
 * Time: 17:19
 * Email: bpteam22@gmail.com
 */

function showPic($img,$ex,$prefix=0)
{
	$dirToSave='tmp/';
	if(is_array($img))
	{
		foreach ($img as $key => $value)
		{
			if(is_array($value)) showPic($value,$ex);
			else
			{
				$t=rand();
				$fh=fopen($dirToSave.'img'.$prefix.$t.$key.'.'.$ex,'w+');
				fwrite($fh,'');
				fclose($fh);
				imagepng($value,$dirToSave.'img'.$prefix.$t.$key.'.'.$ex,9);
				echo "<img src='".$dirToSave."img".$prefix.$t.$key.".".$ex."'>||";
			}
		}
	}
	else
	{
		$t=rand();
		$fh=fopen($dirToSave.'img'.$prefix.$t.'.'.$ex,'w+');
		fwrite($fh,'');
		fclose($fh);
		imagepng($img,$dirToSave.'img'.$prefix.$t.'.'.$ex,9);
		echo "<img src='".$dirToSave."img".$prefix.$t.".".$ex."'>||";
	}
}