Set objFSO = CreateObject("Scripting.FileSystemObject")
Set objShell = CreateObject("WScript.Shell")

' Файллар жойлашувини аниқлаш
strPath = objFSO.GetParentFolderName(WScript.ScriptFullName)
strConfigFile = strPath & "\config.ini"

' Бошланғич манзил (Агар файл бўлмаса)
strAppURL = "http://localhost/lifestore"

' Созламалардан URL-ни ўқиш
If objFSO.FileExists(strConfigFile) Then
    Set objFile = objFSO.OpenTextFile(strConfigFile, 1)
    Do Until objFile.AtEndOfStream
        strLine = objFile.ReadLine
        If InStr(strLine, "AppURL=") > 0 Then
            strAppURL = Mid(strLine, InStr(strLine, "=") + 1)
        End If
    Loop
    objFile.Close
End If

' Microsoft Edge-ни App режимида ишга тушириш
' Бу браузернинг манзил қаторисиз, худди дастурдек очилишини таъминлайди
strCommand = "msedge.exe --app=" & strAppURL & " --window-size=1200,800"

' Буйруқни яширинча (0) ижро этиш
objShell.Run strCommand, 0, False
