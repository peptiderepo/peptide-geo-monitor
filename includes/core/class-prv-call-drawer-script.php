<?php
/**
 * Inline JS for the PR Vision call-detail drawer (v0.3.0).
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Emits the inline JavaScript for the call-detail drawer.
 *
 * Split from PRV_Call_Drawer_Renderer to keep each class under 300 lines.
 * Handles: row-click open, AJAX fetch, close (button + Esc), copy buttons,
 * and focus-management (return focus to triggering row on close).
 *
 * Security: data comes via AJAX from PRV_Call_Detail_Ajax which enforces
 * the capture allowlist — no keys or headers can appear in drawer content.
 *
 * Who triggers: PRV_Call_Drawer_Renderer::render_container().
 * Dependencies: PRV_Call_Detail_Ajax (server side); wp_create_nonce().
 *
 * @see class-prv-call-drawer-renderer.php — Calls render_script().
 * @see class-prv-call-detail-ajax.php     — Server AJAX handler.
 * @package PrVision
 */
class PRV_Call_Drawer_Script {

	/**
	 * Emit the inline script tag.
	 *
	 * Side effects: Outputs script HTML.
	 *
	 * @param string $nonce Ajax nonce for prv_call_drawer action.
	 * @param string $ajax  Admin AJAX URL.
	 *
	 * @return void
	 */
	public static function render( string $nonce, string $ajax ): void {
		// Escape values for embedding in JS string literals.
		$nonce_js = esc_js( $nonce );
		$ajax_js  = esc_url( $ajax );

		echo '<script>';
		echo '(function(){';
		echo 'var drawer=document.getElementById("prv-drawer");';
		echo 'var body=document.getElementById("prv-drawer-body");';
		echo 'var closeBtn=document.getElementById("prv-drawer-close");';
		echo 'var lastFocus=null;';
		echo 'function openDrawer(callId,triggerEl){';
		echo '  lastFocus=triggerEl||null;';
		echo '  drawer.removeAttribute("hidden");';
		echo '  body.setAttribute("aria-busy","true");';
		echo '  var skel=document.querySelector(".prv-drawer-skeleton");';
		echo '  if(skel){body.innerHTML=skel.outerHTML;}';
		echo '  closeBtn.focus();';
		echo '  loadCall(callId);';
		echo '}';
		echo 'function closeDrawer(){';
		echo '  drawer.setAttribute("hidden","");';
		echo '  body.setAttribute("aria-busy","false");';
		echo '  if(lastFocus&&lastFocus.focus){lastFocus.focus();}';
		echo '}';
		echo 'function loadCall(callId){';
		echo '  var data=new URLSearchParams();';
		echo '  data.append("action","prv_call_detail");';
		echo '  data.append("nonce","' . $nonce_js . '");'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js applied above
		echo '  data.append("call_id",callId);';
		echo '  fetch("' . $ajax_js . '",{method:"POST",credentials:"same-origin",body:data})'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url applied above
		echo '    .then(function(r){return r.json();})';
		echo '    .then(function(json){';
		echo '      if(json.success){';
		echo '        body.innerHTML=json.data.html;';
		echo '        body.setAttribute("aria-busy","false");';
		echo '        attachCopyButtons();';
		echo '      }else{';
		echo '        body.innerHTML="<p class=\'prv-drawer-err\'>"+json.data+"</p>";';
		echo '        body.setAttribute("aria-busy","false");';
		echo '      }';
		echo '    })';
		echo '    .catch(function(){';
		echo '      body.innerHTML="<p class=\'prv-drawer-err\'>Error loading call details.</p>";';
		echo '      body.setAttribute("aria-busy","false");';
		echo '    });';
		echo '}';
		echo 'function attachCopyButtons(){';
		echo '  document.querySelectorAll(".prv-copy-btn").forEach(function(btn){';
		echo '    btn.addEventListener("click",function(){';
		echo '      var target=document.getElementById(btn.dataset.copyTarget);';
		echo '      if(target){';
		echo '        var orig=btn.textContent;';
		echo '        navigator.clipboard.writeText(target.textContent||"").then(function(){';
		echo '          btn.textContent="Copied!";';
		echo '          setTimeout(function(){btn.textContent=orig;},1500);';
		echo '        });';
		echo '      }';
		echo '    });';
		echo '  });';
		echo '}';
		echo 'closeBtn.addEventListener("click",closeDrawer);';
		echo 'document.addEventListener("keydown",function(e){';
		echo '  if("Escape"===e.key&&!drawer.hasAttribute("hidden")){closeDrawer();}';
		echo '});';
		echo 'document.querySelectorAll(".prv-call-row").forEach(function(row){';
		echo '  function handleOpen(){openDrawer(row.dataset.callId,row);}';
		echo '  row.addEventListener("click",handleOpen);';
		echo '  row.addEventListener("keydown",function(e){';
		echo '    if("Enter"===e.key||" "===e.key){e.preventDefault();handleOpen();}';
		echo '  });';
		echo '});';
		echo '})();';
		echo '</script>';
	}
}
