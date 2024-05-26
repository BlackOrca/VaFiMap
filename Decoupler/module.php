<?php

declare(strict_types=1);
class Decoupler extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('Source', 0);
        
        $this->RegisterPropertyBoolean('IsLowFilterActive', false);
        $this->RegisterPropertyFloat('LowFilterValue', 0);

        $this->RegisterPropertyBoolean('IsHighFilterActive', false);
        $this->RegisterPropertyFloat('HighFilterValue', 0);
        
        $this->RegisterAttributeInteger('SelectedType', 1);
        $this->RegisterPropertyBoolean('IsSelectedTypeLocked', false);

        $this->RegisterPropertyBoolean('UseValueInverting', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $sourceId = $this->ReadPropertyInteger('Source');
      
        if (!IPS_VariableExists($sourceId)) {
            $this->SetStatus(200);
            return;
        } 
        else {
            $this->SetStatus(102);
        }

        $this->MaintainValueVariable($sourceId);

        //Unregister first
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);                
            }
        }
        if($sourceId > 0)
            $this->UnregisterReference($sourceId);

        //Register
        $this->RegisterMessage($sourceId, VM_UPDATE);
        $this->RegisterReference($sourceId);
      
        $this->Filter();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('Sender ' . $SenderID, 'Message ' . $Message, 0);
        if ($Message === VM_UPDATE) {
            $this->Filter();
        }
    }

    private function MaintainValueVariable($sourceId)
    {
        if($this->ReadPropertyBoolean('IsSelectedTypeLocked'))
            return;

        //Get Variable infos to define the output value type and profile
        $sourceInfo = IPS_GetVariable($sourceId);
        
        $variableProfile = "";
        if($sourceInfo['VariableCustomProfile'] != "")
            $variableProfile = $sourceInfo['VariableCustomProfile'];
        else if($sourceInfo['VariableProfile'] != "")
            $variableProfile = $sourceInfo['VariableProfile'];
        
        $this->UnregisterVariable('Value');
        
            //0: Boolean, 1: Integer, 2: Float, 3: String
        if($sourceInfo['VariableType'] == 1) $this->RegisterVariableInteger('Value', 'Value', $variableProfile, 0);
        else if($sourceInfo['VariableType'] == 2) $this->RegisterVariableFloat('Value', 'Value', $variableProfile, 0);
        else if($sourceInfo['VariableType'] == 0) $this->RegisterVariableBoolean('Value', 'Value', $variableProfile, 0);
        else return;

        $this->WriteAttributeInteger('SelectedType', $sourceInfo['VariableType']);
    }

    private function Filter(): bool
    {
        $sourceId = $this->ReadPropertyInteger('Source');

        if (!IPS_VariableExists($sourceId)) return false;

        $sourceValue = GetValue($sourceId);

        if($this->ReadAttributeInteger('SelectedType') == 1 || $this->ReadAttributeInteger('SelectedType') == 2)
        {
            if($this->ReadPropertyBoolean('IsLowFilterActive'))
            {
                if($sourceValue <= $this->ReadPropertyFloat('LowFilterValue'))
                    return false;
            }

            if($this->ReadPropertyBoolean('IsHighFilterActive'))
            {
                if($sourceValue >= $this->ReadPropertyFloat('HighFilterValue'))
                    return false;
            }
        }
        
        return $this->Map($sourceValue);
    }

    private function Map($value): bool
    {  
        $oldValue = $this->GetValue('Value');
        
        //- 1 zu 1 
        //Invertieren bool != bool oder *-1
        //Immer Aktuallisieren oder nur bei werte änderung

        if(!$this->ReadPropertyBoolean('UseValueInverting'))
        {
            if($oldValue != $value) $this->SetValue('Value', $value);
        }
        else
        {
            if($this->ReadAttributeInteger('SelectedType') == 1 || $this->ReadAttributeInteger('SelectedType') == 2)
            {
                $newValue = $value *-1;
                if($newValue != $oldValue) $this->SetValue('Value', $newValue);
            }
            else if($this->ReadAttributeInteger('SelectedType') == 0)
            {
                $newBoolValue = !$value;
                if($newBoolValue != $oldValue) $this->SetValue('Value', $newBoolValue);
            }
        }            
      
        return true;
    }

    public function VariableSelected(int $id)
    {
        $this->UpdateFormField('test', 'visible', false);
    }

    public function GetConfigurationForm()
    {
        $sourceId = $this->ReadPropertyInteger('Source');
        $variableLocked = $this->ReadPropertyBoolean('IsSelectedTypeLocked');
        $sourceType = IPS_GetVariable($sourceId)['VariableType'];
        $invertValue = $this->ReadPropertyBoolean('UseValueInverting');
        
        $form = [
            'elements' => [
                [ //0
                    'type' => 'RowLayout',
                    'items' => [                        
                        [ //0
                            'type' => 'CheckBox',
                            'name' => 'IsSelectedTypeLocked',
                            'caption' => 'Lock Selected Type',
                            'value' => \$variableLocked
                        ],
                        [ //1                        
                            'type' => 'Label',
                            'caption' => 'At first configure must this checkbox to off.',
                            'italic' => true,
                            'width' => '80%'
                        ]
                    ]
                ],
                [ //1
                    'type' => 'RowLayout',
                    'items' => [                        
                        [ //0
                            'type' => 'SelectVariable',
                            'caption' => 'Source Variable',
                            'name' => 'Source',
                            'validVariableTypes' => [
                                0,
                                1,
                                2
                            ],
                            'value' => \$sourceId
                        ],
                        [ //1
                            'type' => 'Label',
                            'caption' => 'Supported are Integer, Float and Boolean.',
                            'italic' => true,
                            'width' => '80%'
                        ]
                    ]
                ],
                [ //2
                    'type' => 'RowLayout',
                    'items' => [
                        [ //0
                            'type' => 'CheckBox',
                            'name' => 'IsLowFilterActive',
                            'caption' => 'Low Filter Active',
                            'visible' => \$sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                        ],
                        [ //1
                            'type' => 'NumberSpinner',
                            'name' => 'LowFilterValue',
                            'caption' => 'Low Filter Value',
                            'visible' => \$sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                        ]
                    ]
                ],
                [ //3
                    'type' => 'RowLayout',
                    'items' => [
                        [
                            'type' => 'CheckBox',
                            'name' => 'IsHighFilterActive',
                            'caption' => 'High Filter Active',
                            'visible' => \$sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                        ],
                        [
                            'type' => 'NumberSpinner',
                            'name' => 'HighFilterValue',
                            'caption' => 'High Filter Value',
                            'visible' => \$sourceType == VARIABLETYPE_INTEGER || $sourceType == VARIABLETYPE_FLOAT
                        ]
                    ]
                ],
                [ //4
                    'type' => 'CheckBox',
                    'name' => 'UseValueInverting',
                    'caption' => 'Use Value Inverting',
                    'value' => \$invertValue
                ]                
            ],
            'actions' => [
                'type' => 'RowLayout',
                'items' => [
                    [
                        'type' => 'Label',
                        "caption" => "This module is only for the not Commercial use!"
                    ],
                    [
                        "type" => "Image",
                        "image" => "data:image/png;base64,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"
                    ]
                ]
            ],
            'status' => [
                [
                    'code' => 200,
                    'icon' => 'error',
                    'caption' => 'No Source Variable selected'
                ]
            ]
        ];   

        return JSON_encode($form);
    }
}
