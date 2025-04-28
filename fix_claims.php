<?php
/**
 * This script fixes the missing catch block in claims.php
 */

// Read the file
$file = 'admin/claims.php';
$content = file_get_contents($file);

// Find the position where we need to add the catch block
$search = "                }
            }
        }";

$replace = "                }
            } catch (Exception \$e) {
                error_log(\"Error processing notification for claim ID \$claimId: \" . \$e->getMessage());
            }
        }";

// Replace the content
$newContent = str_replace($search, $replace, $content);

// Write back to the file
if (file_put_contents($file, $newContent)) {
    echo "Successfully fixed the missing catch block in claims.php\n";
} else {
    echo "Failed to update claims.php\n";
}
?>
