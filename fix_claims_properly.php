<?php
/**
 * This script properly fixes the claims.php file by:
 * 1. Removing the incorrectly placed catch block
 * 2. Adding the catch block in the correct location
 */

// Read the file
$file = 'admin/claims.php';
$content = file_get_contents($file);

// First, remove the incorrectly placed catch block
$wrongCatch = "            } catch (Exception \$e) {
                error_log(\"Error processing notification for claim ID \$claimId: \" . \$e->getMessage());
            }";
$content = str_replace($wrongCatch, "            }", $content);

// Now add the catch block in the correct location (after the email notification section)
$search = "                    } else {
                        error_log(\"No recipients found for claim ID: \$claimId with approver role: \$approverRole\");
                    }
                }
            }
        }";

$replace = "                    } else {
                        error_log(\"No recipients found for claim ID: \$claimId with approver role: \$approverRole\");
                    }
                }
            } catch (Exception \$e) {
                error_log(\"Error processing notification for claim ID \$claimId: \" . \$e->getMessage());
            }
        }";

// Replace the content
$newContent = str_replace($search, $replace, $content);

// Write back to the file
if (file_put_contents($file, $newContent)) {
    echo "Successfully fixed the claims.php file with the catch block in the correct location\n";
} else {
    echo "Failed to update claims.php\n";
}
?>
