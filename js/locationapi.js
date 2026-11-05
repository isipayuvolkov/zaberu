class LocationAPI {
    static async getUserLocation() {
        try {
            const response = await fetch('https://ipwho.is/?lang=ru');
            const data = await response.json();
            if (data.success && data.city) {
                return { success: true, city: data.city, source: 'ipwho.is' };
            }
        } catch (error) {
            console.log('ipwho.is не сработал:', error);
        }
  
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            if (data.city) {
                return { success: true, city: data.city, source: 'ipapi.co' };
            }
        } catch (error) {
            console.log('ipapi.co не сработал:', error);
        }
  
        return { success: false, message: 'Не удалось определить местоположение' };
    }

    static async autoFillCity(inputId, labelId) {
        const inputElement = document.getElementById(inputId);
        if (!inputElement) return;

        const result = await this.getUserLocation();
        if (result.success) {
            inputElement.value = result.city;
            
            // Если передан ID для подсказки, показываем ее на 3 секунды
            if (labelId) {
                const labelElement = document.getElementById(labelId);
                if (labelElement) {
                    labelElement.textContent = `Город определен автоматически (${result.source})`;
                    labelElement.style.display = 'block';
                    setTimeout(() => {
                        labelElement.style.display = 'none';
                    }, 3000);
                }
            }
        }
    }
}