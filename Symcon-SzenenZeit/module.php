<?
class SzenenSteuerungZeit extends IPSModule {

	public function Create() {
		//Never delete this line!
		parent::Create();

		//These lines are parsed on Symcon Startup or Instance creation
		//You cannot use variables here. Just static values.
		$this->RegisterPropertyInteger("SceneCount", 2);
		$this->RegisterPropertyBoolean("CycleThrough", true);
		$this->RegisterPropertyBoolean("Loop", false);
		
		if(!IPS_VariableProfileExists("SZS.SceneControl")){
			IPS_CreateVariableProfile("SZS.SceneControl", 1);
			IPS_SetVariableProfileValues("SZS.SceneControl", 1, 2, 0);
			//IPS_SetVariableProfileIcon("SZS.SceneControl", "");
			IPS_SetVariableProfileAssociation("SZS.SceneControl", 1, "Speichern", "", -1);
			IPS_SetVariableProfileAssociation("SZS.SceneControl", 2, "Ausführen", "", -1);
		}
		if(!IPS_VariableProfileExists("SZS.Minutes")){
			IPS_CreateVariableProfile("SZS.Minutes", 1);
			IPS_SetVariableProfileValues("SZS.Minutes", 0, 120, 1);
			IPS_SetVariableProfileText("SZS.Minutes",""," Min.");
			//IPS_SetVariableProfileIcon("SZS.Minutes", "");
		}
		if(!IPS_VariableProfileExists("SZS.StartStopButton")){
			IPS_CreateVariableProfile("SZS.StartStopButton", 1);
			IPS_SetVariableProfileValues("SZS.StartStopButton", 1, 1, 0);
			//IPS_SetVariableProfileIcon("SZS.StartStopButton", "");
			IPS_SetVariableProfileAssociation("SZS.StartStopButton", 1, "Start", "", -1);
		}

	}

	public function Destroy() {
		//Never delete this line!
		parent::Destroy();
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();
		
		$this->CreateCategoryByIdent($this->InstanceID, "Targets", "Targets");
		
		//SetValue Script
		if(@IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID) === false)
		{
			$svid = IPS_CreateScript(0 /* PHP Script */);
			IPS_SetParent($svid, $this->InstanceID);
			IPS_SetName($svid, "SetValue");
			IPS_SetIdent($svid, "SetValueScript");
			IPS_SetHidden($svid, true);	
			IPS_SetScriptContent($svid, "<?

if (\$IPS_SENDER == \"WebFront\") 
{ 
    SetValue(\$_IPS['VARIABLE'], \$_IPS['VALUE']); 
} 

?>");
		}
		// </!!>
		// <!!>
		if(@IPS_GetObjectIDByIdent("Status", $this->InstanceID) === false)
		{
			$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
			$vid = IPS_CreateVariable(1 /* StartStop */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Durchlauf");
			IPS_SetIdent($vid, "Status");
			IPS_SetVariableCustomProfile($vid, "SZS.StartStopButton");
			IPS_SetVariableCustomAction($vid,$svid);
		}
		if(@IPS_GetObjectIDByIdent("StatusEvent", $this->InstanceID) !== false)
		{
			$vid = IPS_GetObjectIDByIdent("StatusEvent", $this->InstanceID);
			IPS_DeleteEvent($vid);
		}
			$svid = IPS_GetObjectIDByIdent("Status", $this->InstanceID);
			$vid = IPS_CreateEvent(0 /* Ausgelößtes Event */);
			IPS_SetParent($vid, $this->InstanceID);
			IPS_SetName($vid, "Status OnRefresh");
			IPS_SetIdent($vid, "StatusEvent");
			IPS_SetEventTrigger($vid,0 /*bei aktuallisierung*/, $svid);
			IPS_SetEventScript($vid,'<?

$association = IPS_GetVariableProfile("SZS.StartStopButton")["Associations"]; 
if($association[0]["Name"] == "Stop")
{	
	//Change Caption of the Button
	IPS_SetVariableProfileAssociation("SZS.StartStopButton", 1, "Start", "", -1);
	
	$targetIDs = IPS_GetObjectIDByIdent("Targets", '. $this->InstanceID .');
	
	//set all targets to 0 or false
	foreach(IPS_GetChildrenIDs($targetIDs) as $TargetID) {
		//only allow links
		if(IPS_LinkExists($TargetID)) {
			$linkVariableID = IPS_GetLink($TargetID)[\'TargetID\'];
			if(IPS_VariableExists($linkVariableID)) {
				$type = IPS_GetVariable($linkVariableID)[\'VariableType\'];
				$id = $linkVariableID;
				
				$o = IPS_GetObject($id);
				$v = IPS_GetVariable($id);
				
				if($v["VariableCustomAction"] > 0)
					$actionID = $v["VariableCustomAction"];
				else
					$actionID = $v["VariableAction"];
				
				//Skip this device if we do not have a proper id
					if($actionID < 10000)
						continue;
					
				switch($type)
				{
					case(0):
						$value = false; break;
					case(1):
						$value = 0; break;
					case(2):
						$value = 0.0; break;
					case(3):
						$value = ""; break;				
				}
					
				if(IPS_InstanceExists($actionID)) {
					IPS_RequestAction($actionID, $o["ObjectIdent"], $value);
				}
				else if(IPS_ScriptExists($actionID))
				{
					echo IPS_RunScriptWaitEx($actionID, Array("VARIABLE" => $id, "VALUE" => $value));
				}
			}
		}
	}
	
	if(@IPS_GetObjectIDByIdent("TimerEvent", '. $this->InstanceID .') !== false)
	{
		$timerID = IPS_GetObjectIDByIdent("TimerEvent", '. $this->InstanceID .');
		IPS_DeleteEvent($timerID);
	}
}
else
{	
	IPS_SetVariableProfileAssociation("SZS.StartStopButton", 1, "Stop", "", -1);
	
	//Timer
	if("'.$this->ReadPropertyBoolean("Loop").'" == "1")
	{
		$loop = 1;
	}
	else
	{
		$loop = 0;
	}
	
	$svid = IPS_GetObjectIDByIdent("Timer1", '. $this->InstanceID .');
	$vid = IPS_CreateEvent(1 /* zyklisch */);
	IPS_SetParent($vid, '. $this->InstanceID .');
	IPS_SetName($vid, "Cycling Timer");
	IPS_SetIdent($vid, "TimerEvent");
	
	IPS_SetPosition($vid, 3);
	IPS_SetEventCyclicTimeBounds($vid,time() + GetValue($svid)*60 + 1,time() + GetValue($svid)*60 + 1);
	IPS_SetEventActive($vid,true);
	IPS_SetEventScript($vid,"<?
	
IPS_SetEventActive($vid,false);
IPS_Sleep(100);

\$loop = $loop;
\$x = IPS_GetObject($vid)[\"ObjectPosition\"] / 2 + 0.5;
if(@IPS_GetObjectIDByIdent(\"Scene\".\$x, '. $this->InstanceID .') !== false)
{
	\$data = wddx_deserialize(GetValue(IPS_GetObjectIDByIdent(\"Scene\".\$x.\"Data\", '. $this->InstanceID .')));
	\$timerTime = GetValue(IPS_GetObjectIDByIdent(\"Timer\".\$x, '. $this->InstanceID .'));
		if(\$data != NULL && \$timerTime != 0) {
			foreach(\$data as \$id => \$value) {
				if (IPS_VariableExists(\$id)){
					\$o = IPS_GetObject(\$id);
					\$v = IPS_GetVariable(\$id);
					if(\$v[\"VariableCustomAction\"] > 0)
						\$actionID = \$v[\"VariableCustomAction\"];
					else
						\$actionID = \$v[\"VariableAction\"];
					
					//Skip this device if we do not have a proper id
					if(\$actionID < 10000)
					{
						SetValue(\$id,\$value);
						continue;
					}
					
					if(IPS_InstanceExists(\$actionID)) {
						IPS_RequestAction(\$actionID, \$o[\"ObjectIdent\"], \$value);
					} else if(IPS_ScriptExists(\$actionID)) {
						echo IPS_RunScriptWaitEx(\$actionID, Array(\"VARIABLE\" => \$id, \"VALUE\" => \$value));
					}
				}
			}
		} else {
			echo \"No SceneData for this Scene\";
		}

	\$svid = IPS_GetObjectIDByIdent(\"Timer\". \$x, '. $this->InstanceID .');
	IPS_SetPosition($vid, IPS_GetObject($vid)[\"ObjectPosition\"] + 2);
	IPS_SetEventCyclicTimeBounds($vid,time()+1+GetValue(\$svid)*60,time()+1+GetValue(\$svid)*60);
	IPS_Sleep(100);
	IPS_SetEventActive($vid,true);
}
else if(\$loop == 1)
{
	
	\$data = wddx_deserialize(GetValue(IPS_GetObjectIDByIdent(\"Scene1Data\", '. $this->InstanceID .')));
	\$timerTime = GetValue(IPS_GetObjectIDByIdent(\"Timer1\", '. $this->InstanceID .'));
		
		if(\$data != NULL && \$timerTime != 0) {
			foreach(\$data as \$id => \$value) {
				if (IPS_VariableExists(\$id)){
					\$o = IPS_GetObject(\$id);
					\$v = IPS_GetVariable(\$id);
					if(\$v[\"VariableCustomAction\"] > 0)
						\$actionID = \$v[\"VariableCustomAction\"];
					else
						\$actionID = \$v[\"VariableAction\"];
					
					//Skip this device if we do not have a proper id
					if(\$actionID < 10000)
					{
						SetValue(\$id,\$value);
						continue;
					}
						
					if(IPS_InstanceExists(\$actionID)) {
						IPS_RequestAction(\$actionID, \$o[\"ObjectIdent\"], \$value);
					} else if(IPS_ScriptExists(\$actionID)) {
						echo IPS_RunScriptWaitEx(\$actionID, Array(\"VARIABLE\" => \$id, \"VALUE\" => \$value));
					}
				}
			}
		} else {
			echo \"No SceneData for this Scene\";
		}
	
	\$svid = IPS_GetObjectIDByIdent(\"Timer1\",'. $this->InstanceID .');
	IPS_SetPosition($vid, 3);
	IPS_SetEventCyclicTimeBounds($vid,time()+1+GetValue(\$svid)*60,time()+1+GetValue(\$svid)*60);
	IPS_Sleep(100);
	IPS_SetEventActive($vid,true);
}
else
{
	IPS_SetVariableProfileAssociation(\"SZS.StartStopButton\", 1, \"Start\", \"\", -1);
	
	\$targetIDs = IPS_GetObjectIDByIdent(\"Targets\", '. $this->InstanceID .');
	
	//set all targets to 0 or false
	foreach(IPS_GetChildrenIDs(\$targetIDs) as \$TargetID) {
		//only allow links
		if(IPS_LinkExists(\$TargetID)) {
			\$linkVariableID = IPS_GetLink(\$TargetID)[\'TargetID\'];
			if(IPS_VariableExists(\$linkVariableID)) {
				\$type = IPS_GetVariable(\$linkVariableID)[\'VariableType\'];
				\$id = \$linkVariableID;
				
				\$o = IPS_GetObject(\$id);
				\$v = IPS_GetVariable(\$id);
				
				if(\$v[\"VariableCustomAction\"] > 0)
					\$actionID = \$v[\"VariableCustomAction\"];
				else
					\$actionID = \$v[\"VariableAction\"];
				
				//Skip this device if we do not have a proper id
					if(\$actionID < 10000)
						continue;
					
				switch(\$type)
				{
					case(0):
						\$value = false; break;
					case(1):
						\$value = 0; break;
					case(2):
						\$value = 0.0; break;
					case(3):
						\$value = \"\"; break;				
				}
					
				if(IPS_InstanceExists(\$actionID)) {
					IPS_RequestAction(\$actionID, \$o[\"ObjectIdent\"], \$value);
				}
				else if(IPS_ScriptExists(\$actionID))
				{
					echo IPS_RunScriptWaitEx(\$actionID, Array(\"VARIABLE\" => \$id, \"VALUE\" => \$value));
				}
			}
		}
	}
	
	IPS_DeleteEvent($vid);
}

?>");
	
	//event run first scene
	$data = wddx_deserialize(GetValue(IPS_GetObjectIDByIdent("Scene1Data", '. $this->InstanceID .')));
	$timerTime = GetValue(IPS_GetObjectIDByIdent("Timer1", '. $this->InstanceID .'));
		if($data != NULL && $timerTime != 0) {
			foreach($data as $id => $value) {
				if (IPS_VariableExists($id)){
					$o = IPS_GetObject($id);
					$v = IPS_GetVariable($id);
					if($v["VariableCustomAction"] > 0)
						$actionID = $v["VariableCustomAction"];
					else
						$actionID = $v["VariableAction"];
					
					//Skip this device if we do not have a proper id
					if($actionID < 10000)
					{
						SetValue($id,$value);
						continue;
					}
					
					if(IPS_InstanceExists($actionID)) {
						IPS_RequestAction($actionID, $o["ObjectIdent"], $value);
					} else if(IPS_ScriptExists($actionID)) {
						echo IPS_RunScriptWaitEx($actionID, Array("VARIABLE" => $id, "VALUE" => $value));
					}
				}
			}
		} else {
			echo "No SceneData for this Scene";
		}
}
?>');
IPS_SetEventActive($vid, true); 
		// </!!>
		
		for($i = 1; $i <= $this->ReadPropertyInteger("SceneCount"); $i++) {
			//TimerValues
			if($this->ReadPropertyBoolean("CycleThrough"))
			{
				if(@IPS_GetObjectIDByIdent("Timer".$i, $this->InstanceID) === false)
				{
					$svid = IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
					$vid = IPS_CreateVariable(1 /* TimerValues */);
					IPS_SetParent($vid, $this->InstanceID);
					IPS_SetName($vid, "Timer".$i);
					IPS_SetPosition($vid, ($i*2+1) /* odd */);
					IPS_SetIdent($vid, "Timer".$i);
					IPS_SetVariableCustomProfile($vid, "SZS.Minutes");
					IPS_SetVariableCustomAction($vid,$svid);
					$this->EnableAction("Timer".$i);
					SetValue($vid, 0);
				}
			}
			
			if(@IPS_GetObjectIDByIdent("Scene".$i, $this->InstanceID) === false){
				//Scene
				$vid = IPS_CreateVariable(1 /* Scene */);
				IPS_SetParent($vid, $this->InstanceID);
				IPS_SetName($vid, "Scene".$i);
				IPS_SetPosition($vid, ($i*2) /* even */);
				IPS_SetIdent($vid, "Scene".$i);
				IPS_SetVariableCustomProfile($vid, "SZS.SceneControl");
				$this->EnableAction("Scene".$i);
				SetValue($vid, 2);
				//SceneData
				$vid = IPS_CreateVariable(3 /* SceneData */);
				IPS_SetParent($vid, $this->InstanceID);
				IPS_SetName($vid, "Scene".$i."Data");
				IPS_SetIdent($vid, "Scene".$i."Data");
				IPS_SetHidden($vid, true);				
			}
		}
		//Delete excessive Scences		
		if($this->ReadPropertyBoolean("CycleThrough"))
		{
			$ChildrenIDsCount = (sizeof(IPS_GetChildrenIDs($this->InstanceID))-3)/3;
		}
		else
		{
			$ChildrenIDsCount = (sizeof(IPS_GetChildrenIDs($this->InstanceID))-3)/3;
			for($k = 1; $k < ($ChildrenIDsCount); $k++)
			{
				if(@IPS_GetObjectIDByIdent("Timer".$k, $this->InstanceID) !== false)
				{
					IPS_DeleteVariable(IPS_GetObjectIDByIdent("Timer".$k, $this->InstanceID));
				}
			}
			$ChildrenIDsCount = (sizeof(IPS_GetChildrenIDs($this->InstanceID))-3)/2;
		}
		if($ChildrenIDsCount > $this->ReadPropertyInteger("SceneCount")) {
			for($j = $this->ReadPropertyInteger("SceneCount")+1; $j <= $ChildrenIDsCount; $j++) {
				IPS_DeleteVariable(IPS_GetObjectIDByIdent("Scene".$j, $this->InstanceID));
				IPS_DeleteVariable(IPS_GetObjectIDByIdent("Scene".$j."Data", $this->InstanceID));
				if($this->ReadPropertyBoolean("CycleThrough"))
				{
					if(@IPS_GetObjectIDByIdent("Timer".$j, $this->InstanceID) !== false)
					{
						IPS_DeleteVariable(IPS_GetObjectIDByIdent("Timer".$j, $this->InstanceID));
					}
				}
			}
		}
	}

	public function RequestAction($Ident, $Value) {
		
		switch($Value) {
			case "1":
				$this->SaveValues($Ident);
				break;
			case "2":
				$this->CallValues($Ident);
				break;
			default:
				throw new Exception("Invalid action");
		}
	}

	public function CallScene(int $SceneNumber){
		
		$this->CallValues("Scene".$SceneNumber);

	}

	public function SaveScene(int $SceneNumber){
		
		$this->SaveValues("Scene".$SceneNumber);

	}

	private function SaveValues($SceneIdent) {
		
		$targetIDs = IPS_GetObjectIDByIdent("Targets", $this->InstanceID);
		$data = Array();
		
		//We want to save all Lamp Values
		foreach(IPS_GetChildrenIDs($targetIDs) as $TargetID) {
			//only allow links
			if(IPS_LinkExists($TargetID)) {
				$linkVariableID = IPS_GetLink($TargetID)['TargetID'];
				if(IPS_VariableExists($linkVariableID)) {
					$data[$linkVariableID] = GetValue($linkVariableID);
				}
			}
		}
		SetValue(IPS_GetObjectIDByIdent($SceneIdent."Data", $this->InstanceID), wddx_serialize_value($data));
	}

	private function CallValues($SceneIdent) {
		
		$data = wddx_deserialize(GetValue(IPS_GetObjectIDByIdent($SceneIdent."Data", $this->InstanceID)));
		
		if($data != NULL) {
			foreach($data as $id => $value) {
				if (IPS_VariableExists($id)){
					$o = IPS_GetObject($id);
					$v = IPS_GetVariable($id);
					if($v['VariableCustomAction'] > 0)
						$actionID = $v['VariableCustomAction'];
					else
						$actionID = $v['VariableAction'];
					
					//Skip this device if we do not have a proper id
					if($actionID < 10000)
						continue;
					
					if(IPS_InstanceExists($actionID)) {
						IPS_RequestAction($actionID, $o['ObjectIdent'], $value);
					} else if(IPS_ScriptExists($actionID)) {
						echo IPS_RunScriptWaitEx($actionID, Array("VARIABLE" => $id, "VALUE" => $value));
					}
				}
			}
		} else {
			echo "No SceneData for this Scene";
		}
	}

	private function CreateCategoryByIdent($id, $ident, $name) {
		 $cid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($cid === false) {
			 $cid = IPS_CreateCategory();
			 IPS_SetParent($cid, $id);
			 IPS_SetName($cid, $name);
			 IPS_SetIdent($cid, $ident);
		 }
		 return $cid;
	}

}
?>