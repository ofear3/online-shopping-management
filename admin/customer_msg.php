<?php
declare(strict_types=1);

require_once __DIR__ . '/header.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// Message deletion logic
if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $delStmt = $conn->prepare("DELETE FROM user_messages WHERE id = ?");
    if ($delStmt) {
        $delStmt->bind_param("i", $deleteId);
        $delStmt->execute();
        $delStmt->close();
    }
    $_SESSION['toast_message'] = ($currentLang === 'bn') ? 'মেসেজটি সফলভাবে মুছে ফেলা হয়েছে।' : 'Message deleted successfully.';
    header("Location: customer_msg.php");
    exit;
}

// Fetch messages from database (latest first)
$messages = [];
$result = $conn->query("SELECT * FROM user_messages ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}
?>

<div class="p-6 md:p-8 space-y-8">

    <!-- Page Header Banner -->
    <div class="bg-gradient-to-r from-zinc-950 via-zinc-900 to-indigo-950 rounded-3xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 border border-zinc-800/80">
        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="space-y-2 text-center md:text-left relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 font-bold text-[11px] rounded-full uppercase tracking-wider">
                📬 Admin Inbox
            </span>
            <h1 class="text-2xl md:text-4xl font-black tracking-tight">
                <?= $currentLang === 'bn' ? 'কাস্টমার মেসেজসমূহ' : 'Customer Messages' ?>
            </h1>
            <p class="text-xs md:text-sm text-zinc-400">
                <?= $currentLang === 'bn' ? 'গ্রাহকদের পাঠানো সমস্ত জিজ্ঞাসা এবং ফিডব্যাক এখানে দেখা যাবে।' : 'Manage all customer inquiries and support messages sent from the contact form.' ?>
            </p>
        </div>
        <div class="px-5 py-3 rounded-2xl bg-zinc-900/90 border border-zinc-800 text-center shadow-inner relative z-10">
            <span class="block text-[10px] text-zinc-400 uppercase tracking-widest font-bold">Total Messages</span>
            <span class="text-2xl font-black text-indigo-400"><?= count($messages) ?></span>
        </div>
    </div>

    <!-- Messages Table Card -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between">
            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                <?= $currentLang === 'bn' ? 'ইনবক্স লিস্ট' : 'Inbox List' ?>
            </h3>
        </div>

        <?php if (empty($messages)): ?>
            <!-- Empty State -->
            <div class="p-16 text-center space-y-3">
                <div class="w-16 h-16 bg-zinc-800/80 text-zinc-400 rounded-2xl flex items-center justify-center mx-auto shadow-inner border border-zinc-700/50">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-xs font-bold text-zinc-400">
                    <?= $currentLang === 'bn' ? 'কোনো নতুন মেসেজ নেই।' : 'No messages found in the inbox.' ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Table View -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-zinc-950/60 border-b border-zinc-800 text-zinc-400 uppercase tracking-wider text-[11px] font-bold">
                            <th class="py-3.5 px-4">#ID</th>
                            <th class="py-3.5 px-4">Sender Info</th>
                            <th class="py-3.5 px-4">Subject</th>
                            <th class="py-3.5 px-4">Message</th>
                            <th class="py-3.5 px-4">Received At</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300 font-medium">
                        <?php foreach ($messages as $msg): ?>
                            <tr class="hover:bg-zinc-800/40 transition">
                                <td class="py-3.5 px-4 font-bold text-white">#<?= (int)$msg['id'] ?></td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-zinc-100"><?= e($msg['name']) ?></div>
                                    <div class="text-[11px] text-zinc-400"><?= e($msg['email']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-indigo-400 max-w-xs truncate"><?= e($msg['subject']) ?></td>
                                <td class="py-3.5 px-4 text-zinc-300 max-w-sm">
                                    <p class="line-clamp-2 leading-relaxed"><?= e($msg['message']) ?></p>
                                </td>
                                <td class="py-3.5 px-4 text-zinc-400 text-[11px] whitespace-nowrap">
                                    <?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="customer_msg.php?delete_id=<?= (int)$msg['id'] ?>" 
                                       onclick="return confirm('<?= $currentLang === 'bn' ? 'আপনি কি নিশ্চিত এই মেসেজটি ডিলিট করতে চান?' : 'Are you sure you want to delete this message?' ?>');"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-bold rounded-xl transition text-[11px]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <span>Delete</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</main>
</body>
</html>