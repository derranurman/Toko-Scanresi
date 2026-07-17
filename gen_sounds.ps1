Add-Type -AssemblyName System.Speech

$synth = New-Object System.Speech.Synthesis.SpeechSynthesizer

Write-Host "=== Installed voices ==="
foreach ($v in $synth.GetInstalledVoices()) {
    $info = $v.VoiceInfo
    Write-Host ("{0} | {1} | {2}" -f $info.Name, $info.Culture, $info.Gender)
}

# Pilih voice perempuan kalau ada
$female = $synth.GetInstalledVoices() | Where-Object { $_.VoiceInfo.Gender -eq 'Female' } | Select-Object -First 1
if ($female) {
    $synth.SelectVoice($female.VoiceInfo.Name)
    Write-Host ("Using voice: {0}" -f $female.VoiceInfo.Name)
} else {
    Write-Host "No female voice found, using default."
}

$outDir = Join-Path $PSScriptRoot "public\sounds"
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }

# Teks per file. Pitch dinaikkan via SSML <prosody> supaya kesan suara imut/anime.
$items = @(
    @{ file = "sukses.wav"; text = "Sukses Packed!" },
    @{ file = "sudah.wav";  text = "Sudah Packed ya" },
    @{ file = "gagal.wav";  text = "Gagal! Coba lagi" }
)

foreach ($item in $items) {
    $path = Join-Path $outDir $item.file
    $synth.SetOutputToWaveFile($path)
    $ssml = "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='en-US'><prosody pitch='+45%' rate='+10%'>" + $item.text + "</prosody></speak>"
    $synth.SpeakSsml($ssml)
    Write-Host ("Generated: {0}" -f $path)
}

$synth.SetOutputToNull()
$synth.Dispose()
Write-Host "DONE"
