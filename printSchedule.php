<?php
require_once('Connections/hms.php'); // Uses $hms (mysqli)
session_start();

if (!isset($_SESSION['UserID'])) {
    header("Location:index.php");
    exit();
}

// Modern SQL Value Helper
function GetSQLValueString($con, $theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "") 
{
  $theValue = $con->real_escape_string($theValue);

  switch ($theType) {
    case "text":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;    
    case "long":
    case "int":
      $theValue = ($theValue != "") ? intval($theValue) : "NULL";
      break;
    case "double":
      $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
      break;
    case "date":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;
    case "defined":
      $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
      break;
  }
  return $theValue;
}

$col_Batch = "-1";
if (isset($_SESSION['period'])) {
  $col_Batch = $_SESSION['period'];
}

// Fetch Schedule
$query_Batch = sprintf("SELECT loameschedule.loanamount, loameschedule.loanid, loameschedule.cheque_no, loameschedule.date_on_cheque, loameschedule.periodid, 
loameschedule.memberid, loameschedule.name 
FROM tbl_bank_schedule AS loameschedule 
WHERE loameschedule.periodid = %s 
ORDER BY loanid ASC", GetSQLValueString($hms, $col_Batch, "text"));

$Batch = $hms->query($query_Batch) or die($hms->error);
$row_Batch = $Batch->fetch_assoc();
$totalRows_Batch = $Batch->num_rows;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque Schedule Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .print-container { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-8 text-slate-800">

    <!-- Print Action Bar -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print">
        <a href="javascript:history.back()" class="flex items-center gap-2 text-slate-500 hover:text-blue-600 transition-colors">
            <span class="material-icons-round">arrow_back</span> Back
        </a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all flex items-center gap-2">
            <span class="material-icons-round">print</span> Print Schedule
        </button>
    </div>

    <!-- Main Report Container -->
    <div class="print-container max-w-4xl mx-auto bg-white p-12 rounded-2xl shadow-xl border border-slate-100">
        
        <!-- Header -->
        <header class="flex items-start justify-between border-b-2 border-slate-100 pb-8 mb-8">
            <div class="flex items-center gap-6">
                <img src="image/mhwun_logo.png" alt="Logo" class="w-20 h-20 object-contain">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-tight text-slate-900 leading-tight">
                        Medical and Health Workers Union of Nigeria<br>
                        <span class="text-blue-700">OOUTH Branch, Sagamu, Ogun State</span>
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">Status Report & Confirmation Schedule</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Date Generated</p>
                <p class="text-lg font-bold text-slate-800"><?php echo date('d/m/Y'); ?></p>
            </div>
        </header>

        <!-- Recipient Info -->
        <div class="mb-10 flex justify-between items-end">
            <div>
                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">To:</p>
                <h2 class="text-2xl font-bold text-slate-900">The Manager</h2>
            </div>
            <div class="text-right">
                <h3 class="text-lg font-bold text-slate-800 bg-slate-50 px-4 py-2 rounded-lg border border-slate-200">
                    CONFIRMATION SCHEDULE OF CHEQUES ISSUED OUT
                </h3>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border border-slate-200 mb-8">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-700 uppercase font-semibold text-xs border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 w-16 text-center">S/N</th>
                        <th class="px-6 py-4">Date on Cheque</th>
                        <th class="px-6 py-4">Payee Name</th>
                        <th class="px-6 py-4 text-center">Cheque No.</th>
                        <th class="px-6 py-4 text-right">Amount (₦)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $i = 1; 
                    $sum = 0; 
                    if ($totalRows_Batch > 0) {
                        do { 
                            $date = $row_Batch['date_on_cheque'] ? date('d-M-Y', strtotime($row_Batch['date_on_cheque'])) : '-';
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 text-center text-slate-500 font-mono"><?php echo $i++; ?></td>
                        <td class="px-6 py-4 text-slate-700 font-medium"><?php echo $date; ?></td>
                        <td class="px-6 py-4 text-slate-900 font-semibold uppercase"><?php echo $row_Batch['name']; ?></td>
                        <td class="px-6 py-4 text-center font-mono text-slate-600 bg-slate-50/50"><?php echo $row_Batch['cheque_no']; ?></td>
                        <td class="px-6 py-4 text-right font-bold font-mono text-slate-800">
                            <?php echo number_format($row_Batch['loanamount'], 2); ?>
                        </td>
                    </tr>
                    <?php 
                            $sum += $row_Batch['loanamount'];
                        } while ($row_Batch = $Batch->fetch_assoc()); 
                    } else {
                    ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 italic">No cheques found for this period.</td>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-right font-bold text-slate-600 uppercase tracking-wider">Total Sum</td>
                        <td class="px-6 py-4 text-right font-black text-lg text-slate-900 font-mono">
                            ₦<?php echo number_format($sum, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Amount In Words -->
        <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-6 mb-12">
            <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-2">Total Amount in Words</p>
            <p id="amountInWords" class="text-xl font-bold text-blue-900 capitalize italic leading-relaxed">
                <!-- JS will populate this -->
                Loading...
            </p>
            <input type="hidden" id="rawSum" value="<?php echo $sum; ?>">
        </div>

        <!-- Approval Section -->
        <div class="grid grid-cols-2 gap-12 mt-16 pt-8 border-t border-slate-100 page-break-inside-avoid">
            <div>
                <div class="h-16 border-b border-slate-300 mb-2"></div>
                <p class="text-sm font-bold text-slate-700 uppercase">Authorized Signature</p>
                <p class="text-xs text-slate-500">Chairman / Secretary</p>
            </div>
            <div>
                <div class="h-16 border-b border-slate-300 mb-2"></div>
                <p class="text-sm font-bold text-slate-700 uppercase">Authorized Signature</p>
                <p class="text-xs text-slate-500">Treasurer / Financial Secretary</p>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="max-w-4xl mx-auto mt-8 text-center text-xs text-slate-400 no-print">
        &copy; <?php echo date("Y"); ?> MHWUN OOUTH Branch. System Generated Report.
    </div>

<script>
    // Custom Number to Words Function
    function numberToWords(amount) {
        const units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        const teens = ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        const scales = ['', 'thousand', 'million', 'billion', 'trillion'];

        function convertChunk(num) {
            let words = [];
            if (num >= 100) {
                words.push(units[Math.floor(num / 100)]);
                words.push('hundred');
                num %= 100;
                if (num > 0) words.push('and');
            }
            if (num >= 20) {
                words.push(tens[Math.floor(num / 10)]);
                num %= 10;
            }
            if (num >= 10 && num < 20) {
                words.push(teens[num - 10]);
                num = 0;
            }
            if (num > 0) {
                words.push(units[num]);
            }
            return words.join(' ');
        }

        if (amount === 0) return 'zero naira only';

        let numStr = parseFloat(amount).toFixed(2);
        let [integerPart, decimalPart] = numStr.split('.');
        
        // Process Integer Part
        let words = [];
        let num = parseInt(integerPart);
        let scaleIdx = 0;

        if (num === 0) words.push('zero');
        
        while (num > 0) {
            let chunk = num % 1000;
            if (chunk > 0) {
                let chunkStr = convertChunk(chunk);
                if (scaleIdx > 0) chunkStr += ' ' + scales[scaleIdx];
                words.unshift(chunkStr);
            }
            num = Math.floor(num / 1000);
            scaleIdx++;
        }

        let result = words.join(', ') + ' naira';

        // Process Kobo
        let kobo = parseInt(decimalPart);
        if (kobo > 0) {
            result += ' and ' + convertChunk(kobo) + ' kobo';
        } else {
            result += ' only';
        }

        return result.charAt(0).toUpperCase() + result.slice(1);
    }

    $(document).ready(function() {
        // Trigger conversion
        const sum = parseFloat($('#rawSum').val());
        $('#amountInWords').text(numberToWords(sum));
    });
</script>

</body>
</html>
<?php
// Close resources if needed
// $Batch->free();
// $hms->close();
?>
