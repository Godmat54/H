<script setup>
import { EquoCommService } from "@equo/comm";
import { ref } from "vue";

const returnedMessage = ref("");

const getMessage = async () => {
  returnedMessage.value = await EquoCommService.send("returnToFront");
};
</script>

<template>
  <div>
    <h5>Get a message from Javascript to Java</h5>
    <div class="codes">
      <img width="30" src="../../assets/js.png" />
      <div>
        <pre><code>const getMessage = async () =&gt; {
  returnedMessage.value = await EquoCommService.send(&quot;returnToFront&quot;);
};</code></pre>
      </div>
      <img width="30" src="../../assets/java.png" />
      <div>
        <pre><code>@Component
public class MyEventHandler implements IActionHandler {

	@Reference
	private ICommService commService;

	public String returnToFront() {
		return "Hello from Java";
	}
}
</code></pre>
      </div>
    </div>
    <div class="try-it">
      <button @click="getMessage">Try it!</button>
      <div />
      <p>{{ returnedMessage }}</p>
    </div>
  </div>
</template>
